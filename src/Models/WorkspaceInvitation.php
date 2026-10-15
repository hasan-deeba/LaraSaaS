<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use LaraSaaS\Support\WorkspaceRole;

/**
 * A pending team invitation: e-mail + workspace-level role + single-use-ish
 * token with expiry. Accepting creates the membership and stamps
 * `accepted_at`; the same e-mail can be re-invited afterwards.
 */
class WorkspaceInvitation extends Model
{
    protected $table = 'larasaas_workspace_invitations';

    protected $guarded = ['id'];

    protected $casts = [
        'workspace_id' => 'integer',
        'invited_by' => 'integer',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.workspaces.model', Workspace::class), 'workspace_id');
    }

    /** Points at whatever model the HOST project configured — never a hardcoded App\Models\User. */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'invited_by');
    }

    public function roleEnum(): WorkspaceRole
    {
        return WorkspaceRole::tryFromLoose((string) $this->role) ?? WorkspaceRole::Member;
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * URL guests can be mailed: hits the authenticated API endpoint that
     * accepts the invitation for the signed-in user.
     */
    public function acceptUrl(): string
    {
        return URL::to('/' . trim(config('larasaas.route.api_prefix', 'api/larasaas'), '/')
            . '/workspaces/invitations/' . $this->token . '/accept');
    }

    /** Shape consumed by API clients / React pages. */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'email' => $this->email,
            'role' => $this->roleEnum()->value,
            'role_label' => $this->roleEnum()->label(),
            'token' => $this->token,
            'accept_url' => $this->acceptUrl(),
            'invited_by' => $this->invited_by,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'pending' => $this->isPending(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
