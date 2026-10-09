<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\AnnouncementController;
use LaraSaaS\Http\Controllers\BillingController;
use LaraSaaS\Http\Controllers\FeatureFlagController;

/*
|--------------------------------------------------------------------------
| LaraSaaS JSON API
|--------------------------------------------------------------------------
|
| Consumed by the shipped React components (<AnnouncementStack />,
| <FeatureGate />, billing pages). Prefix/middleware are configurable.
|
*/


Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
Route::post('/announcements/{announcement}/dismiss', [AnnouncementController::class, 'dismiss'])->name('announcements.dismiss');

Route::get('/features', [FeatureFlagController::class, 'index'])->name('features.index');

Route::get('/subscription', [BillingController::class, 'status'])->name('subscription.status');
