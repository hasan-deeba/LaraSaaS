<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\WorkspaceController;

/*
|--------------------------------------------------------------------------
| LaraSaaS Workspace & Team Routes (Inertia surface)
|--------------------------------------------------------------------------
|
| Loaded only when `larasaas.multi_tenant.enabled` is true — inside the
| group defined in config('larasaas.route'). Prefix, domain, middleware
| and name prefix are all yours to change.
|
*/

Route::middleware([\LaraSaaS\Http\Middleware\ShareLaraSaaSProps::class])->group(function () {

    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');
    Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])->name('workspaces.show');

    Route::post('/workspaces/{workspace}/invitations', [WorkspaceController::class, 'invite'])->name('workspaces.invitations.store');
    Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceController::class, 'revokeInvitation'])->name('workspaces.invitations.destroy');

    Route::put('/workspaces/{workspace}/members/{user}/role', [WorkspaceController::class, 'changeRole'])->name('workspaces.members.role');
    Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])->name('workspaces.members.destroy');

    Route::post('/invitations/{token}/accept', [WorkspaceController::class, 'acceptInvitation'])->name('workspaces.invitations.accept');
});
