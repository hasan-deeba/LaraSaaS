<?php

namespace LaraSaaS\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\BillingActions;

/**
 * Headless billing surface (`integration.mode = api`) — for React SPAs on
 * other servers, Flutter / iOS / Android apps, or any client that renders
 * its own tables & pricing UI and only needs the JSON API.
 */
class BillingApiController extends Controller
{
    public function __construct(protected LaraSaaS $larasaas, protected BillingActions $billing) {}

    /**
     * The complete admin-managed pricing matrix — everything a
     * client needs to render its own pricing table.
     */
    public function pricing(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->larasaas->pricingForUi(),
            'subscription' => $this->larasaas->subscriptionForUi($request),
            'authenticated' => $request->user() !== null,
        ])->header('Cache-Control', 'private, no-store');
    }

    /**
     * Current subscription state for the authenticated billable.
     */
    public function subscription(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->larasaas->subscriptionForUi($request),
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->billing->invoices($request),
        ]);
    }

    /**
     * Start a subscription. Returns the Stripe Checkout URL for the client
     * to open (new tab, WebView, url_launcher…), or reports the swap when
     * the billable was already subscribed.
     *
     * Body: interval (monthly|yearly), optional success_url / cancel_url.
     */
    public function subscribe(Request $request, string $plan): JsonResponse
    {
        $this->larasaas->plan($plan) ?? abort(404);

        $request->validate([
            'interval'     => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
            'success_url'  => ['nullable', 'url', 'max:2048'],
            'cancel_url'   => ['nullable', 'url', 'max:2048'],
        ]);

        $result = $this->billing->subscribe($request, $plan, $request->input('interval'));

        return response()->json([
            'action' => $result['action'],
            'url'    => $result['url'],
            'data'   => $this->larasaas->subscriptionForUi($request),
        ], $result['action'] === 'checkout' ? 201 : 200);
    }

    public function swap(Request $request, string $plan): JsonResponse
    {
        $this->larasaas->plan($plan) ?? abort(404);

        $request->validate([
            'interval' => ['required', Rule::in(config('larasaas.pricing.intervals', ['monthly', 'yearly']))],
        ]);

        $this->billing->swap($request, $plan, $request->input('interval'));

        return response()->json([
            'data' => $this->larasaas->subscriptionForUi($request),
        ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $this->billing->cancel($request);

        return response()->json([
            'data' => $this->larasaas->subscriptionForUi($request),
        ]);
    }

    public function resume(Request $request): JsonResponse
    {
        $this->billing->resume($request);

        return response()->json([
            'data' => $this->larasaas->subscriptionForUi($request),
        ]);
    }

    /**
     * Stripe's hosted customer portal URL — the client opens it in a
     * browser / WebView. Body: optional success_url for the return trip.
     */
    public function portal(Request $request): JsonResponse
    {
        $request->validate([
            'success_url' => ['nullable', 'url', 'max:2048'],
        ]);

        return response()->json([
            'url' => $this->billing->portalUrl($request),
        ]);
    }
}
