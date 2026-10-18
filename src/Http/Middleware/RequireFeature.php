<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate any host route behind a Pennant flag, with optional workspace scope:
 *
 *   Route::get('/labs', ...)->middleware('larasaas.feature:ai-assistant');
 */
class RequireFeature
{
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        $user = $request->user();
        $scope = $user;

        if ($user && method_exists($user, 'currentWorkspace') && $user->currentWorkspace()) {
            $scope = [$user, $user->currentWorkspace()];
        }

        if (! Feature::for($scope)->active($flag)) {
            if ($request->expectsJson()) {
                abort(404);
            }

            abort(404, __('larasaas::larasaas.flags.unavailable'));
        }

        return $next($request);
    }
}
