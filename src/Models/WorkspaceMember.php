<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's membership in a workspace, carrying the granular workspace-level
 * role: Owner, Admin or Member. Swap this class via
 * `multi_tenant.membership_model`.
 */
class WorkspaceMember extends Model
{
    public const ROLE_OWNER  = 'owner';
    public const ROLE_ADMIN  = 'admin';
    public const ROLE_MEMBER = 'member';

    protected $table = 'larasaas_workspace_members';

    protected $guarded = ['id'];

    /** Points at whatever model the HOST configured — never a hardcoded App\Models\User. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.multi_tenant.workspace_model', Workspace::class), 'workspace_id');
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    /**
     * Does the membership hold the given role? Honours the `implies` chain
     * from `multi_tenant.roles` (an Owner is also an Admin and a Member).
     */
    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        $definitions = config('larasaas.multi_tenant.roles', []);

        $held = [$this->role];
        $definition = $definitions[$this->role] ?? null;

        if (is_array($definition)) {
            $held = array_merge($held, (array) ($definition['implies'] ?? []));
        }

        return (bool) array_intersect($roles, $held);
    }
}
