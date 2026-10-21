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
        $trial   = $this->trialDays($billable, $planConfig, $name);

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

    /**
     * Trial length for a new Checkout: the plan's own `trial_days`, else
     * `billing.trial_days`. Only the first subscription gets a trial, so
     * cancelling and re-subscribing never earns a second one.
     */
    protected function trialDays($billable, array $planConfig, string $name): int
    {
        if ($this->hasSubscribedBefore($billable, $name)) {
            return 0;
        }

        return max(0, (int) ($planConfig['trial_days'] ?? config('larasaas.billing.trial_days')));
    }

    /** Cashier keeps ended subscriptions, so any row means "subscribed before". */
    protected function hasSubscribedBefore($billable, string $name): bool
    {
        return $billable->subscriptions()->where('type', $name)->exists();
    }

    /**
     * Swap between plans / intervals: immediately invoices and collects payment
     * (`always_invoice` + `error_if_incomplete`) so unpaid upgrades can never
     * activate, and ends any free trial when upgrading or switching to a
     * non-trial plan.
     */
    public function swap(Request $request, string $plan, string $interval): void
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        $this->guardPrice($planConfig, $plan, $interval);

        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        abort_unless($billable->subscribed($name), 403);

        $subscription = $billable->subscription($name);
        $targetPrice  = $planConfig[$interval]['stripe_id'];

        if (! $subscription->onGracePeriod() && $subscription->stripe_price === $targetPrice) {
            return;
        }

        if ($subscription->onTrial() && $this->shouldEndTrialOnSwap($subscription->stripe_price, $planConfig, $interval)) {
            $subscription->skipTrial();
        }

        try {
            $subscription->errorIfPaymentFails()->swapAndInvoice($targetPrice);
        } catch (\Throwable $e) {
            if ($this->isSwapPaymentFailure($e)) {
                abort(402, __('larasaas::larasaas.billing.payment_failed'));
            }

            throw $e;
        }
    }

    /**
     * End an active free trial whenever switching to a higher-cost plan/cycle
     * or to a plan that does not offer a trial — never allow cheap-trial →
     * expensive-plan upgrades without immediate payment.
     */
    protected function shouldEndTrialOnSwap(?string $currentPriceId, array $targetPlan, string $targetInterval): bool
    {
        $targetTrialDays = (int) ($targetPlan['trial_days'] ?? config('larasaas.billing.trial_days'));

        if ($targetTrialDays <= 0) {
            return true;
        }

        $current     = $this->larasaas->priceDetails($currentPriceId);
        $targetPrice = $targetPlan[$targetInterval]['price'] ?? null;

        if ($current === null || ! is_numeric($current['price'] ?? null) || ! is_numeric($targetPrice)) {
            return true;
        }

        $currentAmount  = (float) $current['price'];
        $targetAmount   = (float) $targetPrice;
        $currentMonthly = $current['interval'] === 'yearly' ? $currentAmount / 12 : $currentAmount;
        $targetMonthly  = $targetInterval === 'yearly' ? $targetAmount / 12 : $targetAmount;

        return $targetAmount > $currentAmount || $targetMonthly > $currentMonthly;
    }

    protected function isSwapPaymentFailure(\Throwable $e): bool
    {
        if ($e instanceof \Laravel\Cashier\Exceptions\IncompletePayment
            || $e instanceof \Stripe\Exception\CardException) {
            return true;
        }

        return $e instanceof \Stripe\Exception\InvalidRequestException
            && str_contains(strtolower($e->getMessage()), 'payment');
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
