<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\BillingActions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standalone, frameable UI (`integration.mode = embed|all`) — server-rendered
 * pages with zero frontend build step, designed to live inside an <iframe>
 * or a mobile WebView. Authentication rides on signed URLs produced by
 * `LaraSaaS::embedUrl($user, …)`.
 */
class EmbedController extends Controller
{
    public const NOTICES = [
        'success'      => 'billing.success',
        'canceled'     => 'billing.checkout_canceled',
        'swapped'      => 'billing.swapped',
        'canceled_sub' => 'billing.canceled',
        'resumed'      => 'billing.resumed',
    ];

    public function __construct(protected LaraSaaS $larasaas, protected BillingActions $billing) {}

    public function pricing(Request $request): Response
    {
        $user     = $request->user();
        $interval = $this->interval($request);

        return response()->view('larasaas::embed.pricing', [
            'user'         => $user,
            'interval'     => $interval,
            'pricing'      => $this->larasaas->pricingForUi(),
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'notice'       => $this->notice($request),
            'nav'          => [
                'pricing' => $this->larasaas->embedUrl($user, 'pricing', ['interval' => $interval]),
                'billing' => $user ? $this->larasaas->embedUrl($user, 'billing') : null,
            ],
            'intervals'    => collect(config('larasaas.pricing.intervals', ['monthly', 'yearly']))
                ->mapWithKeys(fn (string $i) => [$i => $this->larasaas->embedUrl($user, 'pricing', ['interval' => $i])])
                ->all(),
            'subscribe'    => collect($user ? $this->larasaas->planKeys() : [])
                ->mapWithKeys(fn (string $key) => [$key => $this->larasaas->embedUrl($user, 'billing.subscribe', ['plan' => $key])])
                ->all(),
            'returns'      => $user ? $this->returnUrls($user, $interval) : [],
        ]);
    }

    public function billing(Request $request): Response
    {
        $user = $request->user();

        return response()->view('larasaas::embed.billing', [
            'user'         => $user,
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'pricing'      => $this->larasaas->pricingForUi(),
            'invoices'     => $this->billing->invoices($request),
            'notice'       => $this->notice($request),
            'nav'          => [
                'pricing' => $this->larasaas->embedUrl($user, 'pricing'),
                'billing' => $user ? $this->larasaas->embedUrl($user, 'billing') : null,
            ],
            'actions'      => [
                'portal' => $this->larasaas->embedUrl($user, 'billing.portal'),
                'cancel' => $this->larasaas->embedUrl($user, 'billing.cancel'),
                'resume' => $this->larasaas->embedUrl($user, 'billing.resume'),
            ],
            'subscribe'    => collect($user ? $this->larasaas->planKeys() : [])
                ->mapWithKeys(fn (string $key) => [$key => $this->larasaas->embedUrl($user, 'billing.subscribe', ['plan' => $key])])
                ->all(),
            'returns'      => $this->returnUrls($user),
        ]);
    }

    /**
     * Subscribe / switch plan → Stripe Checkout opens in the top-level
     * window (forms post with target="_top").
     */
    public function subscribe(Request $request, string $plan): Response
    {
        $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        $result = $this->billing->subscribe($request, $plan, $interval);

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
        return redirect()->away($this->billing->portalUrl($request));
    }

    protected function backToBilling(Request $request, string $notice): Response
    {
        return redirect()->to($this->larasaas->embedUrl($request->user(), 'billing', ['notice' => $notice]));
    }

    /**
     * Where Stripe sends the user back after Checkout / the customer
     * portal. Signed with a generous TTL — customers can take a while
     * inside Stripe — and used verbatim so the signature stays valid.
     *
     * @return array{success: string, canceled: string}
     */
    protected function returnUrls($user, ?string $interval = null): array
    {
        $minutes = 24 * 60;

        $canceled = ['notice' => 'canceled'];

        if ($interval !== null) {
            $canceled['interval'] = $interval;
        }

        return [
            'success'  => $this->larasaas->embedUrl($user, 'billing', ['notice' => 'success'], $minutes),
            'canceled' => $this->larasaas->embedUrl($user, 'pricing', $canceled, $minutes),
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
}
