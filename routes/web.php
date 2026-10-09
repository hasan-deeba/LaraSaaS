<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\Admin\AnnouncementAdminController;
use LaraSaaS\Http\Controllers\Admin\FeatureFlagAdminController;
use LaraSaaS\Http\Controllers\BillingController;
use LaraSaaS\Http\Controllers\DashboardController;
use LaraSaaS\Http\Middleware\ShareLaraSaaSProps;

/*
|--------------------------------------------------------------------------
| LaraSaaS Web Routes
|--------------------------------------------------------------------------
|
| Loaded inside the group defined in config('larasaas.route') — prefix,
| domain, middleware and name prefix are all yours to change.
|
*/

Route::middleware([ShareLaraSaaSProps::class])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/pricing', [BillingController::class, 'pricing'])->name('pricing');

    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('/billing/subscribe/{plan}', [BillingController::class, 'subscribe'])->name('billing.subscribe');
    Route::post('/billing/swap/{plan}', [BillingController::class, 'swap'])->name('billing.swap');
    Route::post('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/resume', [BillingController::class, 'resume'])->name('billing.resume');
    Route::get('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    /*
    |----------------------------------------------------------------------
    | Admin panels — announcements & feature flags
    |----------------------------------------------------------------------
    */
    Route::middleware(config('larasaas.route.admin_middleware', ['web', 'auth', 'can:larasaas-admin']))
        ->prefix('admin')->as('admin.')->group(function () {

            Route::get('/announcements', [AnnouncementAdminController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [AnnouncementAdminController::class, 'store'])->name('announcements.store');
            Route::put('/announcements/{announcement}', [AnnouncementAdminController::class, 'update'])->name('announcements.update');
            Route::delete('/announcements/{announcement}', [AnnouncementAdminController::class, 'destroy'])->name('announcements.destroy');

            Route::get('/features', [FeatureFlagAdminController::class, 'index'])->name('features.index');
            Route::post('/features/{flag}/activate', [FeatureFlagAdminController::class, 'activate'])->name('features.activate');
            Route::post('/features/{flag}/deactivate', [FeatureFlagAdminController::class, 'deactivate'])->name('features.deactivate');
        });
});
