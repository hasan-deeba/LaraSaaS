<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A multi-tenant workspace (organization / team / company). The owner is
 * whatever model the HOST configured in `larasaas.user_model` — never a
 * hardcoded App\Models\User. Swap this class via
 * `multi_tenant.workspace_model` (extend it to keep the relations).
 */
class Workspace extends Model
{
    protected $table = 'larasaas_workspaces';

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
    ];

    /** Points at the configured user model. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'owner_id');
    }

    /** @return HasMany<WorkspaceMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(config('larasaas.multi_tenant.membership_model', WorkspaceMember::class), 'workspace_id');
    }

    /** Pending/accepted invitations for this workspace. */
    public function invitations(): HasMany
    {
        return $this->hasMany(config('larasaas.multi_tenant.invitation_model', WorkspaceInvitation::class), 'workspace_id');
    }
}
