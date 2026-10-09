<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stripe Billing via Laravel Cashier — subscription provisioning, plan
 * swaps, cancellation/resume, and Stripe's secure customer portal.
 */
class BillingController extends Controller
{
    public function __construct(protected LaraSaaS $larasaas) {}

    public function pricing(Request $request): \Inertia\Response
    {
        return Inertia::render($this->larasaas->page('Pricing'), [
            'pricing'      => $this->larasaas->pricingForUi(),
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'notice'       => $request->session()->get('larasaas.notice'),
        ]);
    }

    public function index(Request $request): \Inertia\Response
    {
        return Inertia::render($this->larasaas->page('Billing'), [
            'pricing'      => $this->larasaas->pricingForUi(),
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'invoices'     => $this->invoices($request),
        ]);
    }

    /**
     * Provision a new subscription through Stripe Checkout (Cashier).
     */
    public function subscribe(Request $request, string $plan): Response
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        $priceId = $planConfig[$interval]['stripe_id'] ?? null;

        abort_unless($priceId, 422, __('larasaas::larasaas.billing.missing_price', ['plan' => $plan, 'interval' => $interval]));

        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        if ($billable->subscribed($name)) {
            return $this->swap($request, $plan);
        }

        $trial = $planConfig['trial_days']
            ?? ($billable->hasEverSubscribed($name) ? 0 : config('larasaas.billing.trial_days'));

        $checkout = $billable
            ->newSubscription($name, $priceId)
            ->when($trial, fn ($checkout) => $checkout->trialDays((int) $trial))
            ->when(config('larasaas.billing.allow_promotion_codes'), fn ($checkout) => $checkout->allowPromotionCodes())
            ->when(config('larasaas.billing.collect_tax_ids'), fn ($checkout) => $checkout->collectTaxIds())
            ->checkout([
                'success_url' => route($this->larasaas->routeName('billing.index')) . '?success=1',
                'cancel_url'  => route($this->larasaas->routeName('pricing')) . '?canceled=1',
            ]);

        return Inertia::location($checkout->url);
    }

    /**
     * Swap between plans / intervals (prorated by Cashier).
     */
    public function swap(Request $request, string $plan): Response
    {
        $planConfig = $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        $priceId  = $planConfig[$interval]['stripe_id'] ?? null;
        $billable = $this->larasaas->billable($request) ?? abort(403);
        $name     = $this->larasaas->subscriptionName();

        abort_unless($billable->subscribed($name), 403);

        $billable->subscription($name)->swap($priceId);

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.swapped'));
    }

    public function cancel(Request $request): Response
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);

        $billable->subscription($this->larasaas->subscriptionName())?->cancel();

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.canceled'));
    }

    public function resume(Request $request): Response
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);
        $subscription = $billable->subscription($this->larasaas->subscriptionName());

        abort_unless($subscription?->onGracePeriod(), 403);

        $subscription->resume();

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.resumed'));
    }

    /**
     * Stripe's hosted customer portal — invoices, cards, cancellation.
     */
    public function portal(Request $request): Response
    {
        $billable = $this->larasaas->billable($request) ?? abort(403);

        return Inertia::location(
            $billable->billingPortalUrl(route($this->larasaas->routeName('billing.index')))
        );
    }

    /**
     * JSON state for the React components.
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->larasaas->subscriptionForUi($request));
    }

    protected function invoices(Request $request): array
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
}
