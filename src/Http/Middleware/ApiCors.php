<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Minimal CORS support for browser SPAs served from another origin
 * (`integration.api.cors_origins`). Native clients (Flutter, iOS,
 * server-side) don't need CORS — leave the list empty.
 */
class ApiCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin  = $request->headers->get('Origin');
        $allowed = config('larasaas.integration.api.cors_origins', []);
        $allowed = is_array($allowed) ? $allowed : [];

        $matches = $origin !== null
            && (in_array('*', $allowed, true) || in_array($origin, $allowed, true));

        if ($matches) {
            $response = $request->isMethod('OPTIONS') ? response('', 204) : $next($request);

            $response->headers->set('Access-Control-Allow-Origin', in_array('*', $allowed, true) ? '*' : $origin);
            $response->headers->set('Vary', 'Origin');

            if (! in_array('*', $allowed, true)) {
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
            }

            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', $request->headers->get('Access-Control-Request-Headers', 'Content-Type, Authorization, X-Requested-With, Accept'));
            $response->headers->set('Access-Control-Max-Age', '86400');

            return $response;
        }

        return $next($request);
    }
}
