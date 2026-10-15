<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LaraSaaS\Support\WorkspaceRole;

/**
 * A user's membership in a workspace, carrying the granular workspace-level
 * role (Owner, Admin, Member).
 */
class WorkspaceMember extends Model
{
    protected $table = 'larasaas_workspace_members';

    protected $guarded = ['id'];

    protected $casts = [
        'workspace_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.workspaces.model', Workspace::class), 'workspace_id');
    }

    /** Points at whatever model the HOST project configured — never a hardcoded App\Models\User. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'user_id');
    }

    public function roleEnum(): WorkspaceRole
    {
        return WorkspaceRole::tryFromLoose((string) $this->role) ?? WorkspaceRole::Member;
    }

    public function isOwner(): bool
    {
        return $this->roleEnum() === WorkspaceRole::Owner;
    }

    public function allows(string $permission): bool
    {
        return $this->roleEnum()->allows($permission);
    }

    /** Shape consumed by API clients / React pages. */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'user_id' => $this->user_id,
            'role' => $this->roleEnum()->value,
            'role_label' => $this->roleEnum()->label(),
            'permissions' => $this->roleEnum()->permissions(),
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
