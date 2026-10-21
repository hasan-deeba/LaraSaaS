<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\BillingActions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Standalone, frameable UI (`integration.mode = embed|all`) — server-rendered
 * pages with zero frontend build step, designed to live inside an <iframe>
 * or a mobile WebView. Authentication rides on signed URLs produced by
 * `LaraSaaS::embedUrl($user, …)`.
 */
class EmbedController extends Controller
{
    public const NOTICES = [
        'success'        => 'billing.success',
        'canceled'       => 'billing.checkout_canceled',
        'swapped'        => 'billing.swapped',
        'canceled_sub'   => 'billing.canceled',
        'resumed'        => 'billing.resumed',
        'payment_failed' => 'billing.payment_failed',
    ];

    public const WARN_NOTICES = ['canceled', 'payment_failed'];

    public function __construct(protected LaraSaaS $larasaas, protected BillingActions $billing) {}

    public function pricing(Request $request): Response
    {
        $user     = $request->user();
        $interval = $this->interval($request);

        $this->billing->syncFromStripe($this->larasaas->billable($request));

        return response()->view('larasaas::embed.pricing', [
            'user'         => $user,
            'interval'     => $interval,
            'pricing'      => $this->larasaas->pricingForUi(),
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'notice'       => $this->notice($request),
            'notice_tone'  => $this->noticeTone($request),
            'nav'          => [
                'pricing' => $this->embedLink($request, $user, 'pricing', ['interval' => $interval]),
                'billing' => $user ? $this->embedLink($request, $user, 'billing') : null,
            ],
            'intervals'    => collect(config('larasaas.pricing.intervals', ['monthly', 'yearly']))
                ->mapWithKeys(fn (string $i) => [$i => $this->embedLink($request, $user, 'pricing', ['interval' => $i])])
                ->all(),
            'subscribe'    => collect($user ? $this->larasaas->planKeys() : [])
                ->mapWithKeys(fn (string $key) => [$key => $this->embedLink($request, $user, 'billing.subscribe', ['plan' => $key])])
                ->all(),
        ]);
    }

    public function billing(Request $request): Response
    {
        $user = $request->user();

        $this->billing->syncFromStripe($this->larasaas->billable($request));

        return response()->view('larasaas::embed.billing', [
            'user'         => $user,
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'pricing'      => $this->larasaas->pricingForUi(),
            'invoices'     => $this->billing->invoices($request),
            'notice'       => $this->notice($request),
            'notice_tone'  => $this->noticeTone($request),
            'nav'          => [
                'pricing' => $this->embedLink($request, $user, 'pricing'),
                'billing' => $user ? $this->embedLink($request, $user, 'billing') : null,
            ],
            'actions'      => [
                'portal' => $this->embedLink($request, $user, 'billing.portal'),
                'cancel' => $this->embedLink($request, $user, 'billing.cancel'),
                'resume' => $this->embedLink($request, $user, 'billing.resume'),
            ],
            'subscribe'    => collect($user ? $this->larasaas->planKeys() : [])
                ->mapWithKeys(fn (string $key) => [$key => $this->embedLink($request, $user, 'billing.subscribe', ['plan' => $key])])
                ->all(),
        ]);
    }

    /**
     * Subscribe / switch plan → Stripe Checkout opens in the top-level
     * window (forms post with target="_top"); plan swaps stay in-frame.
     */
    public function subscribe(Request $request, string $plan): Response
    {
        $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        $returns = $this->returnUrls($request->user(), $interval);
        $request->merge([
            'success_url' => $returns['success'],
            'cancel_url'  => $returns['canceled'],
        ]);

        try {
            $result = $this->billing->subscribe($request, $plan, $interval);
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 402) {
                return $this->backToBilling($request, 'payment_failed');
            }

            throw $e;
        }

        if ($result['action'] === 'checkout') {
            return redirect()->away($result['url']);
        }

        return $this->backToBilling($request, 'swapped');
    }

    public function cancel(Request $request): Response
    {
        $this->billing->cancel($request);

        return $this->backToBilling($request, 'canceled_sub');
    }

    public function resume(Request $request): Response
    {
        $this->billing->resume($request);

        return $this->backToBilling($request, 'resumed');
    }

    /** Stripe's hosted customer portal — opens in the top-level window. */
    public function portal(Request $request): Response
    {
        $request->merge([
            'success_url' => $this->larasaas->embedUrl($request->user(), 'billing'),
        ]);

        return redirect()->away($this->billing->portalUrl($request));
    }

    protected function backToBilling(Request $request, string $notice): Response
    {
        return redirect()->to($this->embedLink($request, $request->user(), 'billing', ['notice' => $notice]));
    }

    /**
     * Preserve an incoming signed link's `expires` ceiling across navigation
     * and redirects so refreshing never extends a leaked token's lifetime.
     */
    protected function embedLink(Request $request, $user, string $page = 'pricing', array $params = []): string
    {
        return $this->larasaas->embedUrl($user, $page, $params, $this->currentExpiry($request));
    }

    protected function currentExpiry(Request $request): ?\DateTimeInterface
    {
        $expires = $request->query('expires');

        return is_numeric($expires) ? Carbon::createFromTimestamp((int) $expires) : null;
    }

    /**
     * Where Stripe sends the user back after Checkout — generated server-side
     * only (never exposed in HTML or accepted from untrusted form input).
     *
     * @return array{success: string, canceled: string}
     */
    protected function returnUrls($user, ?string $interval = null): array
    {
        $canceled = ['notice' => 'canceled'];

        if ($interval !== null) {
            $canceled['interval'] = $interval;
        }

        return [
            'success'  => $this->larasaas->embedUrl($user, 'billing', ['notice' => 'success']),
            'canceled' => $this->larasaas->embedUrl($user, 'pricing', $canceled),
        ];
    }

    protected function interval(Request $request): string
    {
        $intervals = config('larasaas.pricing.intervals', ['monthly', 'yearly']);
        $requested = $request->query('interval');

        return in_array($requested, $intervals, true)
            ? $requested
            : config('larasaas.pricing.default_interval', 'monthly');
    }

    protected function notice(Request $request): ?string
    {
        $key = $request->query('notice');

        return is_string($key) && isset(self::NOTICES[$key])
            ? __('larasaas::larasaas.' . self::NOTICES[$key])
            : null;
    }

    protected function noticeTone(Request $request): string
    {
        $key = $request->query('notice');

        return is_string($key) && in_array($key, self::WARN_NOTICES, true) ? 'warn' : 'ok';
    }
}
