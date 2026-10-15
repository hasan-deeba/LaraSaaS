<?php

namespace LaraSaaS\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaraSaaS\Support\WorkspaceRole;

/**
 * A multi-tenant organization: users (host model) belong through
 * `WorkspaceMember` rows with a granular role, and are invited through
 * `WorkspaceInvitation` rows. The owner is mirrored on `owner_id`.
 */
class Workspace extends Model
{
    protected $table = 'larasaas_workspaces';

    protected $guarded = ['id'];

    protected $casts = [
        'owner_id' => 'integer',
    ];

    /*
    |------------------------------------------------------------------
    | Relations
    |------------------------------------------------------------------
    */

    public function members(): HasMany
    {
        return $this->hasMany(config('larasaas.workspaces.member_model', WorkspaceMember::class), 'workspace_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(config('larasaas.workspaces.invitation_model', WorkspaceInvitation::class), 'workspace_id');
    }

    /** Points at whatever model the HOST project configured — never a hardcoded App\Models\User. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('larasaas.user_model', 'App\\Models\\User'), 'owner_id');
    }

    /*
    |------------------------------------------------------------------
    | Membership helpers
    |------------------------------------------------------------------
    */

    public function membershipFor($user): ?WorkspaceMember
    {
        if (! $user) {
            return null;
        }

        return $this->members()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();
    }

    public function roleFor($user): ?WorkspaceRole
    {
        return $this->membershipFor($user)?->roleEnum();
    }

    public function hasMember($user): bool
    {
        return $this->membershipFor($user) !== null;
    }

    public function isOwnedBy($user): bool
    {
        return $user !== null && (int) $this->owner_id === (int) $user->getAuthIdentifier();
    }

    /** Role-based permission check for one user in THIS workspace. */
    public function allows($user, string $permission): bool
    {
        return (bool) $this->roleFor($user)?->allows($permission);
    }

    public function scopeVisibleTo(Builder $query, $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('members', fn (Builder $q) => $q->where('user_id', $user->getAuthIdentifier()));
    }

    /** Accept an id or a slug on `{workspace}` route parameters. */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return $this->where('id', $value)->first() ?? $this->where('slug', $value)->first();
    }

    /** Shape consumed by API clients / React pages. */
    public function toUiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'owner_id' => $this->owner_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
