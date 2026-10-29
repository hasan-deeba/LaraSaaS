<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending team invitation: an e-mail address invited to join a workspace
 * with a specific role. Swap this class via `multi_tenant.invitation_model`.
 */
class WorkspaceInvitation extends Model
{
    protected $table = 'larasaas_workspace_invitations';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.multi_tenant.workspace_model', Workspace::class), 'workspace_id');
    }

    /** The user who sent the invitation (configured user model). */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
