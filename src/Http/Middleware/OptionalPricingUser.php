<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Read a session or configured API guard without making public pricing private. */
class OptionalPricingUser
{
    public function handle(Request $request, Closure $next)
    {
        $guard = config('larasaas.route.pricing_guard');
        if ($guard) {
            $user = Auth::guard($guard)->user();
            $request->setUserResolver(fn () => $user);
        }

        return $next($request);
    }
}
