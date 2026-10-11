<?php

namespace LaraSaaS\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use LaraSaaS\LaraSaaS;

/**
 * Billing operations shared by every integration surface — the Inertia web
 * UI, the headless JSON API and the embeddable pages all call into this.
 */
class BillingActions
{
    public function __construct(protected LaraSaaS $larasaas) {}

    /**
     * Provision a subscription through Stripe Checkout, or swap the plan
     * when the billable is already subscribed.
     *
     * @return array{action: 'checkout'|'swap', url: ?string}
     */
    public function subscribe(Request $request, string $plan, string $interval): array
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        $this->guardPrice($planConfig, $plan, $interval);

        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        if ($billable->subscribed($name)) {
            $this->swap($request, $plan, $interval);

            return ['action' => 'swap', 'url' => null];
        }

        $priceId = $planConfig[$interval]['stripe_id'];
        $trial   = $planConfig['trial_days']
            ?? ($billable->hasEverSubscribed($name) ? 0 : config('larasaas.billing.trial_days'));

        $checkout = $billable
            ->newSubscription($name, $priceId)
            ->when($trial, fn ($checkout) => $checkout->trialDays((int) $trial))
            ->when(config('larasaas.billing.allow_promotion_codes'), fn ($checkout) => $checkout->allowPromotionCodes())
            ->when(config('larasaas.billing.collect_tax_ids'), fn ($checkout) => $checkout->collectTaxIds())
            ->checkout([
                'success_url' => $this->successUrl($request),
                'cancel_url'  => $this->cancelUrl($request),
            ]);

        return ['action' => 'checkout', 'url' => $checkout->url];
    }

    /** Swap between plans / intervals (prorated by Cashier). */
    public function swap(Request $request, string $plan, string $interval): void
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        $this->guardPrice($planConfig, $plan, $interval);

        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        abort_unless($billable->subscribed($name), 403);

        $billable->subscription($name)->swap($planConfig[$interval]['stripe_id']);
    }

    public function cancel(Request $request): void
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);

        $billable->subscription($this->larasaas->subscriptionName())?->cancel();
    }

    public function resume(Request $request): void
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);
        $subscription = $billable->subscription($this->larasaas->subscriptionName());

        abort_unless($subscription?->onGracePeriod(), 403);

        $subscription->resume();
    }

    /** Stripe's hosted customer portal — invoices, cards, cancellation. */
    public function portalUrl(Request $request): string
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);

        return $billable->billingPortalUrl($this->successUrl($request));
    }

    public function invoices(Request $request): array
    {
        $billable = $this->larasaas->billable($request);

        if (! $billable || ! $billable->stripe_id) {
            return [];
        }

        return collect($billable->invoices(true, ['limit' => 6]))
            ->map(fn ($invoice) => [
                'id'     => $invoice->id,
                'date'   => $invoice->date()->toDateString(),
                'total'  => $invoice->total(),
                'status' => $invoice->status,
                'pdf'    => $invoice->invoice_pdf,
            ])
            ->all();
    }

    protected function guardPrice(array $planConfig, string $plan, string $interval): void
    {
        abort_unless(
            $planConfig[$interval]['stripe_id'] ?? null,
            422,
            __('larasaas::larasaas.billing.missing_price', ['plan' => $plan, 'interval' => $interval])
        );
    }

    /**
     * Where Stripe sends the user back after checkout/portal. Priority:
     * explicit client URLs (`success_url` / `cancel_url`), then the embed
     * surface's signed return point (used verbatim so its signature stays
     * valid), then the Inertia billing page when the web UI is registered.
     */
    protected function successUrl(Request $request): string
    {
        return $this->explicitUrl($request, 'success_url')
            ?? $this->safeReturnUrl($request)
            ?? $this->webFallback('billing.index', 'billing', 'success=1');
    }

    protected function cancelUrl(Request $request): string
    {
        return $this->explicitUrl($request, 'cancel_url')
            ?? $this->safeReturnUrl($request)
            ?? $this->webFallback('pricing', 'pricing', 'canceled=1');
    }

    /** Client-provided redirect target — any absolute URL Stripe accepts. */
    protected function explicitUrl(Request $request, string $key): ?string
    {
        $url = $request->input($key);

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /** `_larasaas_return` must stay on our host — never an open redirect. */
    protected function safeReturnUrl(Request $request): ?string
    {
        $return = $request->input('_larasaas_return');

        return is_string($return) && str_starts_with($return, url('/')) ? $return : null;
    }

    protected function webFallback(string $route, string $path, string $query): string
    {
        $name = $this->larasaas->routeName($route);

        if (Route::has($name)) {
            return route($name) . '?' . $query;
        }

        return url(config('larasaas.route.prefix', 'app') . '/' . $path) . '?' . $query;
    }
}
