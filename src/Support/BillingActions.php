<?php

namespace LaraSaaS\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\LaraSaaS;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

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

        // Always reconcile with Stripe first so completed Checkouts are seen
        // immediately even before (or without) webhook delivery — preventing
        // duplicate parallel subscriptions and repeat free trials.
        $this->syncFromStripe($billable);

        if ($this->larasaas->isSubscribed($billable)) {
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
     * activate, ends any free trial when upgrading or switching to a non-trial
     * plan, and cleans up any duplicate parallel subscriptions on the customer.
     */
    public function swap(Request $request, string $plan, string $interval): void
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        $this->guardPrice($planConfig, $plan, $interval);

        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        $this->syncFromStripe($billable);

        $valid = $this->larasaas->validSubscriptions($billable, $name);

        abort_if($valid->isEmpty(), 403);

        /** @var Subscription $subscription */
        $subscription = $valid->first();
        $duplicates   = $valid->slice(1);
        $targetPrice  = $planConfig[$interval]['stripe_id'];

        if ($subscription->stripe_price === $targetPrice) {
            if ($subscription->onGracePeriod()) {
                $subscription->resume();
            }

            $this->terminateDuplicateSubscriptions($duplicates);
            $billable->unsetRelation('subscriptions');

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

        $this->terminateDuplicateSubscriptions($duplicates);
        $billable->unsetRelation('subscriptions');
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
        $name     = $this->larasaas->subscriptionName();

        $this->syncFromStripe($billable);

        $valid = $this->larasaas->validSubscriptions($billable, $name);

        if ($valid->isEmpty()) {
            return;
        }

        /** @var Subscription $primary */
        $primary = $valid->first();

        if (! $primary->onGracePeriod()) {
            $primary->cancel();
        }

        // Terminate any extra duplicate subscriptions immediately so the
        // customer never has leftover active subscriptions in Stripe.
        $this->terminateDuplicateSubscriptions($valid->slice(1));
        $billable->unsetRelation('subscriptions');
    }

    public function resume(Request $request): void
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        $this->syncFromStripe($billable);

        $subscription = $this->larasaas->currentSubscription($billable, $name);

        abort_unless($subscription?->onGracePeriod(), 403);

        $subscription->resume();
        $billable->unsetRelation('subscriptions');
    }

    /** Stripe's hosted customer portal — invoices, cards, cancellation. */
    public function portalUrl(Request $request): string
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);

        $this->syncFromStripe($billable);

        return $billable->billingPortalUrl($this->successUrl($request));
    }

    public function invoices(Request $request): array
    {
        $billable = $this->larasaas->billable($request);

        if (! $billable || ! $billable->stripe_id) {
            return [];
        }

        try {
            return collect($billable->invoices(true, ['limit' => 6]))
                ->map(fn ($invoice) => [
                    'id'     => $invoice->id,
                    'date'   => $invoice->date()->toDateString(),
                    'total'  => $invoice->total(),
                    'status' => $invoice->status,
                    'pdf'    => $invoice->invoice_pdf,
                ])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Reconcile a customer's Stripe subscriptions directly into Cashier's local
     * tables so Checkout redirects, Portal cancellations, and local development
     * without `stripe listen` always stay in sync with Stripe.
     */
    public function syncFromStripe($billable): void
    {
        if (! $billable
            || empty($billable->stripe_id)
            || ! config('cashier.secret')
            || ! method_exists($billable, 'subscriptions')
            || ! $this->hasSubscriptionsTable()) {
            return;
        }

        try {
            $options = method_exists($billable, 'stripeOptions') ? $billable->stripeOptions() : [];
            $remote  = Cashier::stripe($options)->subscriptions->all([
                'customer' => $billable->stripe_id,
                'status'   => 'all',
                'limit'    => 25,
            ]);
        } catch (\Throwable) {
            return;
        }

        $defaultType = $this->larasaas->subscriptionName();

        foreach ($remote->data ?? [] as $stripeSub) {
            $items = $stripeSub->items->data ?? [];

            if ($items === []) {
                continue;
            }

            if (($stripeSub->status ?? null) === 'incomplete_expired') {
                $existing = $billable->subscriptions()->where('stripe_id', $stripeSub->id)->first();
                if ($existing) {
                    $existing->items()->delete();
                    $existing->delete();
                }

                continue;
            }

            $firstItem     = $items[0];
            $isSinglePrice = count($items) === 1;
            $metadata      = is_array($stripeSub->metadata ?? null)
                ? $stripeSub->metadata
                : (is_object($stripeSub->metadata ?? null) && method_exists($stripeSub->metadata, 'toArray')
                    ? $stripeSub->metadata->toArray()
                    : []);
            $type = $metadata['type'] ?? $metadata['name'] ?? $defaultType;

            $trialEndsAt = ! empty($stripeSub->trial_end)
                ? Carbon::createFromTimestamp((int) $stripeSub->trial_end)
                : null;

            $periodEnd = $stripeSub->current_period_end
                ?? ($firstItem->current_period_end ?? null);

            if (($stripeSub->status ?? null) === 'canceled') {
                $trialEndsAt = null;
                $endsAt      = Carbon::createFromTimestamp((int) ($stripeSub->ended_at ?? $stripeSub->canceled_at ?? time()));
            } elseif (! empty($stripeSub->cancel_at_period_end)) {
                $endsAt = ($trialEndsAt && $trialEndsAt->isFuture())
                    ? $trialEndsAt
                    : ($periodEnd ? Carbon::createFromTimestamp((int) $periodEnd) : null);
            } elseif (! empty($stripeSub->cancel_at)) {
                $endsAt = Carbon::createFromTimestamp((int) $stripeSub->cancel_at);
            } else {
                $endsAt = null;
            }

            $subscription = $billable->subscriptions()->firstOrNew([
                'stripe_id' => $stripeSub->id,
            ]);

            if (! $subscription->exists && ! empty($stripeSub->created)) {
                $subscription->created_at = Carbon::createFromTimestamp((int) $stripeSub->created);
            }

            $subscription->fill([
                'type'          => $subscription->type ?: $type,
                'stripe_status' => $stripeSub->status,
                'stripe_price'  => $isSinglePrice ? ($firstItem->price->id ?? null) : null,
                'quantity'      => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
                'trial_ends_at' => $trialEndsAt,
                'ends_at'       => $endsAt,
            ])->save();

            $itemIds = [];

            foreach ($items as $item) {
                $itemIds[] = $item->id;

                $subscription->items()->updateOrCreate(
                    ['stripe_id' => $item->id],
                    [
                        'stripe_product' => is_string($item->price->product) ? $item->price->product : $item->price->product->id,
                        'stripe_price'   => $item->price->id,
                        'quantity'       => $item->quantity ?? null,
                    ],
                );
            }

            $subscription->items()->whereNotIn('stripe_id', $itemIds)->delete();
        }

        $billable->unsetRelation('subscriptions');
    }

    /**
     * @param  Collection<int, Subscription>  $duplicates
     */
    protected function terminateDuplicateSubscriptions(Collection $duplicates): void
    {
        foreach ($duplicates as $duplicate) {
            try {
                $duplicate->cancelNow();
            } catch (\Throwable) {
                $duplicate->skipTrial()->markAsCanceled();
            }
        }
    }

    protected function hasSubscriptionsTable(): bool
    {
        try {
            $modelClass = Cashier::$subscriptionModel;

            return Schema::hasTable((new $modelClass)->getTable());
        } catch (\Throwable) {
            return false;
        }
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
