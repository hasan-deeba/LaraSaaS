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

/*
|--------------------------------------------------------------------------
| Multi-tenant workspaces & team management
|--------------------------------------------------------------------------
|
| Workspace CRUD + invitations/members with granular workspace-level roles.
| `{workspace}` accepts an id or a slug; role authorization rides on the
| `larasaas.workspace.*` middleware (see EnsureWorkspacePermission).
|
*/

Route::get('/workspaces', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'index'])->name('workspaces.index');
Route::post('/workspaces', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'store'])->name('workspaces.store');
Route::post('/workspaces/invitations/{token}/accept', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'acceptInvitation'])->name('workspaces.invitations.accept');

Route::middleware([\LaraSaaS\Http\Middleware\EnsureWorkspaceMember::class])->group(function () {
    Route::get('/workspaces/{workspace}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'show'])->name('workspaces.show');
    Route::patch('/workspaces/{workspace}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'update'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':workspace.update')->name('workspaces.update');
    Route::delete('/workspaces/{workspace}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'destroy'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':workspace.delete')->name('workspaces.destroy');

    Route::get('/workspaces/{workspace}/invitations', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'invitations'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':invitations.view')->name('workspaces.invitations');
    Route::post('/workspaces/{workspace}/invitations', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'invite'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':members.invite')->name('workspaces.invitations.store');
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'revokeInvitation'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':invitations.manage')->name('workspaces.invitations.revoke');

    Route::patch('/workspaces/{workspace}/members/{member}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'updateMemberRole'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':members.update')->name('workspaces.members.update');
    Route::delete('/workspaces/{workspace}/members/{member}', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'removeMember'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':members.remove')->name('workspaces.members.remove');
    Route::post('/workspaces/{workspace}/transfer', [\LaraSaaS\Http\Controllers\Api\WorkspaceApiController::class, 'transferOwnership'])->middleware(\LaraSaaS\Http\Middleware\EnsureWorkspacePermission::class . ':ownership.transfer')->name('workspaces.transfer');
});
