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
    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        /** @var LaraSaaS $larasaas */
        $larasaas = app(LaraSaaS::class);

        // Identity must be in the signed query, never an unsigned POST body.
        abort_if($request->request->has('user'), 403);
        $hasUserParam = $request->query->has('user');
        // Embed POSTs skip CSRF, so they MUST carry a signed user URL even
        // for session users. All rendered forms generate such URLs.
        abort_if(! $request->isMethodSafe() && ! $hasUserParam, 403);
        if ($request->query->has('signature') || $request->query->has('expires') || $request->query->has('ph')) {
            abort_unless(URL::hasValidSignature($request) || URL::hasValidSignature($request, absolute: false), 403);
        }

        $sessionUser = $request->user();

        if ($hasUserParam) {
            // A `user` parameter claims identity — only trust it when signed.
            $signed = URL::hasValidSignature($request)
                || URL::hasValidSignature($request, absolute: false);

            abort_unless($signed, 403, __('larasaas::larasaas.embed.not_authenticated'));

            $user = $larasaas->usersQuery()->find($request->query('user'));

            abort_unless($user !== null, 403, __('larasaas::larasaas.embed.not_authenticated'));

            // Never let a signed link for User A open inside User B's active session.
            abort_if(
                $sessionUser !== null && (string) $sessionUser->getAuthIdentifier() !== (string) $user->getAuthIdentifier(),
                403,
                __('larasaas::larasaas.embed.not_authenticated'),
            );

            if ($request->query->has('ph')) {
                $expected = $larasaas->embedCredentialHash($user);

                abort_unless(
                    $expected !== null && hash_equals($expected, (string) $request->query('ph')),
                    403,
                    __('larasaas::larasaas.embed.not_authenticated'),
                );
            }
        } else {
            $user = $sessionUser;

            abort_unless($user !== null || ($mode === 'optional' && $request->isMethod('GET')), 403, __('larasaas::larasaas.embed.not_authenticated'));
        }

        $request->setUserResolver(fn () => $user);

        if ($hasUserParam) {
            Auth::setUser($user);
        }

        return $next($request);
    }
}
