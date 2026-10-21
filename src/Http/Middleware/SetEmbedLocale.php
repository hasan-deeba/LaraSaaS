<?php

namespace LaraSaaS\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Applies the signed locale carried by server-generated embed URLs. */
class SetEmbedLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->query('locale');
        if (! is_string($locale) || ! preg_match('/^[a-zA-Z]{2,3}(?:[-_][a-zA-Z0-9]{2,8})*$/', $locale)) {
            return $next($request);
        }

        // LaraSaaS ships base-language files (ar, fr, en), so ar_SA resolves
        // to ar rather than silently falling back to English.
        $locale = strtolower(preg_split('/[-_]/', $locale)[0]);
        $originalLocale = app()->getLocale();
        app()->setLocale($locale);

        try {
            return $next($request);
        } finally {
            // Avoid leaking one embed request's locale into another request in
            // Octane / other long-lived application workers.
            app()->setLocale($originalLocale);
        }
    }
}
