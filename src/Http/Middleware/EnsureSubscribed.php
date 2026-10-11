<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LaraSaaS\LaraSaaS;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate any host route behind an active subscription:
 *
 *   Route::get('/reports', ...)->middleware('larasaas.subscribed');
 *   Route::get('/pro-reports', ...)->middleware('larasaas.subscribed:' . 'price_123');
 */
class EnsureSubscribed
{
    public function handle(Request $request, Closure $next, ?string $priceId = null): Response
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = app(LaraSaaS::class);
        $billable = $larasaas->billable($request);

        if (! $billable) {
            abort(403);
        }

        $name = $larasaas->subscriptionName();

        if (! $billable->subscribed($name)) {
            $pricingRoute = $larasaas->routeName('pricing');

            if ($request->expectsJson() || ! $larasaas->servesInertia() || ! \Illuminate\Support\Facades\Route::has($pricingRoute)) {
                abort(402, __('larasaas::larasaas.billing.subscription_required'));
            }

            return redirect()
                ->route($pricingRoute)
                ->with('larasaas.notice', __('larasaas::larasaas.billing.subscription_required'));
        }

        if ($priceId && ! $billable->subscribedToPrice($priceId, $name)) {
            abort(402, __('larasaas::larasaas.billing.plan_required'));
        }

        return $next($request);
    }
}
