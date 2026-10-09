<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\StripeWebhookController;

/*
|--------------------------------------------------------------------------
| Stripe Webhook
|--------------------------------------------------------------------------
|
| Public route (signature-verified by Cashier's middleware). Remember to
| exclude the path from CSRF protection in your host application:
|
|   Laravel 11/12 — bootstrap/app.php:
|       ->withMiddleware(fn ($m) => $m->validateCsrfTokens(except: ['larasaas/webhooks/stripe']))
|
|   Laravel 10 — app/Http/Middleware/VerifyCsrfToken.php: $except[] = 'larasaas/webhooks/stripe';
|
*/

Route::post(config('larasaas.route.webhook_path', 'larasaas/webhooks/stripe'), StripeWebhookController::class)
    ->name(config('larasaas.route.name_prefix', 'larasaas.') . 'webhooks.stripe');
