<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use LaraSaaS\LaraSaaS;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates the embed surface (`integration.mode = embed|all`) for
 * <iframe> / WebView usage — without requiring shared cookies across
 * servers:
 *
 *  • URLs carrying a `user` parameter (produced by
 *    `LaraSaaS::embedUrl($user, …)`) MUST validate as signed (HMAC +
 *    expiry); the user is then resolved from that parameter, so the parent
 *    app never leaks its session to the frame.
 *  • Plain URLs fall back to the normal session user — first-party embeds
 *    (same site) keep working exactly like the rest of your app.
 */
class AuthenticatesEmbedUser
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = app(LaraSaaS::class);

        $hasUserParam = $request->query->has('user') || $request->request->has('user');

        if ($hasUserParam) {
            // A `user` parameter claims identity — only trust it when signed.
            $signed = URL::hasValidSignature($request)
                || URL::hasValidSignature($request, absolute: false);

            abort_unless($signed, 403, __('larasaas::larasaas.embed.not_authenticated'));

            $user = $larasaas->usersQuery()->find($request->query('user') ?? $request->input('user'));

            abort_unless($user !== null, 403, __('larasaas::larasaas.embed.not_authenticated'));
        } else {
            $user = $request->user();

            abort_unless($user !== null, 403, __('larasaas::larasaas.embed.not_authenticated'));
        }

        $request->setUserResolver(fn () => $user);

        if ($hasUserParam) {
            Auth::setUser($user);
        }

        return $next($request);
    }
}
