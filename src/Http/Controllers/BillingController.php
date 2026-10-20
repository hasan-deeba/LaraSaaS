<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\BillingActions;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Stripe Billing via Laravel Cashier — subscription provisioning, plan
 * swaps, cancellation/resume, and Stripe's secure customer portal.
 * Serves the Inertia (same-project) integration surface; headless clients
 * are served by \LaraSaaS\Http\Controllers\Api\BillingApiController.
 */
class BillingController extends Controller
{
    public function __construct(protected LaraSaaS $larasaas, protected BillingActions $billing) {}

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
            'invoices'     => $this->billing->invoices($request),
        ]);
    }

    /**
     * Provision a new subscription through Stripe Checkout (Cashier),
     * or swap plans when the billable is already subscribed.
     */
    public function subscribe(Request $request, string $plan): Response
    {
        $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        try {
            $result = $this->billing->subscribe($request, $plan, $interval);
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 402) {
                return back()->with('larasaas.notice', $e->getMessage());
            }

            throw $e;
        }

        if ($result['action'] === 'checkout') {
            return Inertia::location($result['url']);
        }

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.swapped'));
    }

    /**
     * Swap between plans / intervals (invoiced immediately by Cashier).
     */
    public function swap(Request $request, string $plan): Response
    {
        $this->larasaas->plan($plan) ?? abort(404);

        ['interval' => $interval] = $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        try {
            $this->billing->swap($request, $plan, $interval);
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 402) {
                return back()->with('larasaas.notice', $e->getMessage());
            }

            throw $e;
        }

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.swapped'));
    }

    public function cancel(Request $request): Response
    {
        $this->billing->cancel($request);

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.canceled'));
    }

    public function resume(Request $request): Response
    {
        $this->billing->resume($request);

        return back()->with('larasaas.notice', __('larasaas::larasaas.billing.resumed'));
    }

    /**
     * Stripe's hosted customer portal — invoices, cards, cancellation.
     */
    public function portal(Request $request): Response
    {
        return Inertia::location($this->billing->portalUrl($request));
    }

    /**
     * JSON state for the React components.
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        return response()->json($this->larasaas->subscriptionForUi($request));
    }
}
