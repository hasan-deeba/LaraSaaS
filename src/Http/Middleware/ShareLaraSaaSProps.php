<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use LaraSaaS\LaraSaaS;

/**
 * Shares everything the shipped React pages need with Inertia:
 * translations, accent color and config-safe routing info. Applied only to
 * LaraSaaS routes — your app is untouched.
 */
class ShareLaraSaaSProps
{
    public function handle(Request $request, Closure $next)
    {
        Inertia::share('larasaas', function () use ($request) {
            /** @var LaraSaaS $larasaas */
            $larasaas = app(LaraSaaS::class);
            $user = $request->user();

            return [
                'locale'       => app()->getLocale(),
                'dir'          => $larasaas->textDirection(),
                'translations' => trans('larasaas::larasaas'),
                'accent'       => $larasaas->accentColor(),
                'api_base'     => $larasaas->apiBase(),
                'poll_seconds' => (int) config('larasaas.announcements.poll_seconds', 60),
                'authenticated' => $user !== null,
                'login_url' => config('larasaas.login_url', '/login'),
                'web_base' => '/' . trim(config('larasaas.route.prefix', 'app'), '/'),
                'is_admin'     => $user ? $larasaas->isAdmin($user) : false,
            ];
        });

        return $next($request);
    }
}
