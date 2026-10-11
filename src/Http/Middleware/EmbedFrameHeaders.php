<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the embed surface (`integration.mode = embed|all`) be framed by the
 * origins configured under `integration.embed.frame_ancestors` — the CSP
 * `frame-ancestors` equivalent of X-Frame-Options, which modern browsers
 * prefer and which supports multiple parents.
 */
class EmbedFrameHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $ancestors = config('larasaas.integration.embed.frame_ancestors', ['*']);
        $ancestors = is_array($ancestors) ? $ancestors : [$ancestors];
        $ancestors = array_filter($ancestors);

        if ($ancestors !== []) {
            $response->headers->set(
                'Content-Security-Policy',
                'frame-ancestors ' . implode(' ', $ancestors)
            );
        }

        // Never cache authenticated billing pages.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
