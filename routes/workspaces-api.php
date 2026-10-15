<?php

use Illuminate\Support\Facades\Route;
use LaraSaaS\Http\Controllers\Api\WorkspaceApiController;

/*
|--------------------------------------------------------------------------
| LaraSaaS Workspace & Team JSON API
|--------------------------------------------------------------------------
|
| Loaded only when `larasaas.multi_tenant.enabled` is true — inside the
| API group defined in config('larasaas.route'). Responses use the same
| `{"data": …}` envelope as the billing API.
|
*/

Route::get('/workspaces', [WorkspaceApiController::class, 'index'])->name('workspaces.index');
Route::post('/workspaces', [WorkspaceApiController::class, 'store'])->name('workspaces.store');
Route::get('/workspaces/{workspace}', [WorkspaceApiController::class, 'show'])->name('workspaces.show');
Route::get('/workspaces/{workspace}/members', [WorkspaceApiController::class, 'members'])->name('workspaces.members');

Route::post('/workspaces/{workspace}/invitations', [WorkspaceApiController::class, 'invite'])->name('workspaces.invitations.store');
Route::get('/workspaces/{workspace}/invitations', [WorkspaceApiController::class, 'invitations'])->name('workspaces.invitations');
Route::delete('/workspaces/{workspace}/invitations/{invitation}', [WorkspaceApiController::class, 'revokeInvitation'])->name('workspaces.invitations.destroy');

Route::put('/workspaces/{workspace}/members/{user}/role', [WorkspaceApiController::class, 'changeRole'])->name('workspaces.members.role');
Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceApiController::class, 'removeMember'])->name('workspaces.members.destroy');

Route::get('/invitations/{token}', [WorkspaceApiController::class, 'showInvitation'])->name('workspaces.invitations.show');
Route::post('/invitations/{token}/accept', [WorkspaceApiController::class, 'acceptInvitation'])->name('workspaces.invitations.accept');
