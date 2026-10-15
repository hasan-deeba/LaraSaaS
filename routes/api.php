<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\AnnouncementController;
use LaraSaaS\Http\Controllers\Api\BillingApiController;
use LaraSaaS\Http\Controllers\FeatureFlagController;

/*
|--------------------------------------------------------------------------
| LaraSaaS JSON API
|--------------------------------------------------------------------------
|
| Consumed by the shipped React components (<AnnouncementStack />,
| <FeatureGate />, billing pages) and — in `api` integration mode — by
| headless clients (React on another server, Flutter, native apps) that
| render their own tables & pricing UI. Prefix/middleware are configurable.
|
*/


Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');

Route::get('/features', [FeatureFlagController::class, 'index'])->name('features.index');

/*
| Headless billing surface — everything a custom client needs to build
| its own pricing table and billing flows. Responses use a `{"data": …}`
| envelope (checkout/portal return `{"url": …}` to open).
*/
Route::get('/subscription', [BillingApiController::class, 'subscription'])->name('subscription.status');
Route::get('/invoices', [BillingApiController::class, 'invoices'])->name('invoices');
Route::post('/billing/subscribe/{plan}', [BillingApiController::class, 'subscribe'])->name('billing.subscribe');
Route::post('/billing/swap/{plan}', [BillingApiController::class, 'swap'])->name('billing.swap');
Route::post('/billing/cancel', [BillingApiController::class, 'cancel'])->name('billing.cancel');
Route::post('/billing/resume', [BillingApiController::class, 'resume'])->name('billing.resume');
Route::post('/billing/portal', [BillingApiController::class, 'portal'])->name('billing.portal');
