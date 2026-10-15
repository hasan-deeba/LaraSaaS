<?php

namespace LaraSaaS\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LaraSaaS\Models\Workspace;
use LaraSaaS\Models\WorkspaceMember;
use LaraSaaS\Support\WorkspaceRole;

/**
 * Opt-in trait for the HOST application's user model (`larasaas.user_model`).
 * LaraSaaS never edits your models — add `use HasWorkspaces;` yourself to get
 * workspace/team relations and helpers.
 */
trait HasWorkspaces
{
    /** Every membership row of this user (with the workspace-level role). */
    public function workspaceMemberships(): HasMany
    {
        return $this->hasMany(config('larasaas.workspaces.member_model', WorkspaceMember::class), 'user_id');
    }

    /** Workspaces this user belongs to, via memberships. */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(
            config('larasaas.workspaces.model', Workspace::class),
            (new (config('larasaas.workspaces.member_model', WorkspaceMember::class)))->getTable(),
            'user_id',
            'workspace_id',
        )->withPivot('role');
    }

    /** Workspaces this user owns (billable target for workspace billing). */
    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(config('larasaas.workspaces.model', Workspace::class), 'owner_id');
    }

    /**
     * The user's "current" workspace: the one selected in the session
     * (`larasaas_workspace_id`), falling back to the first membership.
     * `larasaas.billing.workspace_resolver` can point here.
     */
    public function currentWorkspace(): ?Workspace
    {
        if ($id = session('larasaas_workspace_id')) {
            $workspace = $this->workspaces()->whereKey($id)->first();

            if ($workspace) {
                return $workspace;
            }
        }

        $membershipTable = (new (config('larasaas.workspaces.member_model', WorkspaceMember::class)))->getTable();

        return $this->workspaces()->orderBy($membershipTable . '.id')->first();
    }

    /** Remember the active workspace in the session. */
    public function switchWorkspace($workspace): ?Workspace
    {
        if (! $workspace || ! $this->isMemberOf($workspace)) {
            return null;
        }

        session(['larasaas_workspace_id' => $workspace->getKey()]);

        return $workspace;
    }

    public function isMemberOf($workspace): bool
    {
        return (bool) $workspace && $this->workspaceMemberships()
            ->where('workspace_id', $workspace->getKey())
            ->exists();
    }

    public function roleIn($workspace): ?WorkspaceRole
    {
        if (! $workspace) {
            return null;
        }

        $membership = $this->workspaceMemberships()->where('workspace_id', $workspace->getKey())->first();

        return $membership?->roleEnum();
    }

    /** Role-based permission check for one user in one workspace. */
    public function canInWorkspace($workspace, string $permission): bool
    {
        return (bool) $this->roleIn($workspace)?->allows($permission);
    }
}
