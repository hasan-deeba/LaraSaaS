<?php

namespace LaraSaaS\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LaraSaaS\Models\Workspace;
use LaraSaaS\Models\WorkspaceInvitation;
use LaraSaaS\Models\WorkspaceMember;

/**
 * Multi-tenant workspace & team management: workspaces, granular
 * workspace-level roles (Owner, Admin, Member) and the invitation system.
 *
 * Role rules are domain rules and enforced here; the caller still owns
 * authentication (who is logged in) and any extra host policies.
 */
class WorkspaceManager
{
    /* Model resolution — every class is host-swappable via config. */

    public function workspaceClass(): string
    {
        return config('larasaas.multi_tenant.workspace_model') ?: Workspace::class;
    }

    public function membershipClass(): string
    {
        return config('larasaas.multi_tenant.membership_model') ?: WorkspaceMember::class;
    }

    public function invitationClass(): string
    {
        return config('larasaas.multi_tenant.invitation_model') ?: WorkspaceInvitation::class;
    }

    public function roles(): array
    {
        return array_keys(config('larasaas.multi_tenant.roles', []));
    }

    /* Reads */

    public function members(Model $workspace): Collection
    {
        return $workspace->members()->orderBy('role')->orderBy('id')->get();
    }

    public function invitations(Model $workspace): Collection
    {
        return $workspace->invitations()->orderBy('id')->get();
    }

    public function memberFor(Model $workspace, $user): ?WorkspaceMember
    {
        if ($user === null) {
            return null;
        }

        /** @var WorkspaceMember|null $member */
        $member = $workspace->members()->where('user_id', $user->getKey())->first();

        return $member;
    }

    /** Does the user hold one of the given roles on this workspace? */
    public function hasRole(Model $workspace, $user, string|array $roles): bool
    {
        return (bool) $this->memberFor($workspace, $user)?->hasRole($roles);
    }

    /** All workspaces the user belongs to. */
    public function workspacesFor($user): Collection
    {
        $workspaceIds = $this->membershipClass()::query()
            ->where('user_id', $user->getKey())
            ->pluck('workspace_id');

        /** @var Collection<int, Model> $workspaces */
        $workspaces = $this->workspaceClass()::query()
            ->whereIn('id', $workspaceIds)
            ->orderBy('name')
            ->get();

        return $workspaces;
    }

    /** The user's current workspace — the one joined most recently. */
    public function currentWorkspaceFor($user): ?Model
    {
        $membership = $this->membershipClass()::query()
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if (! $membership) {
            return null;
        }

        return $this->workspaceClass()::query()->find($membership->workspace_id);
    }

    /* Writes */

    /** Create a workspace owned by the given user (who becomes its Owner). */
    public function createWorkspace($owner, array $attributes = []): Model
    {
        $data = \Illuminate\Support\Facades\Validator::make($attributes, [
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ])->validate();

        $workspaceClass = $this->workspaceClass();

        return DB::transaction(function () use ($workspaceClass, $data, $owner) {
            /** @var Model $workspace */
            $workspace = new $workspaceClass;
            $workspace->forceFill([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($workspaceClass, $data['slug'] ?? Str::slug($data['name'])),
                'owner_id' => $owner->getKey(),
            ])->save();

            $this->membershipClass()::query()->create([
                'workspace_id' => $workspace->getKey(),
                'user_id' => $owner->getKey(),
                'role' => WorkspaceMember::ROLE_OWNER,
            ]);

            return $workspace;
        });
    }

    /**
     * Invite an e-mail address to join a workspace with a granular role.
     * Owners may assign any role; Admins may assign Admin/Member but never
     * Owner; Members may not invite at all.
     */
    public function invite(Model $workspace, $inviter, string $email, ?string $role = null): WorkspaceInvitation
    {
        $role = $role ?: config('larasaas.multi_tenant.default_role', WorkspaceMember::ROLE_MEMBER);

        if (! in_array($role, $this->roles(), true)) {
            throw ValidationException::withMessages(['role' => "Unknown workspace role [{$role}]."]);
        }

        $this->guardCanAssign($workspace, $inviter, $role);

        $email = strtolower(trim($email));

        $existingUser = $this->findUserByEmail($email);

        if ($existingUser && $this->memberFor($workspace, $existingUser)) {
            throw ValidationException::withMessages(['email' => 'This user is already a member of the workspace.']);
        }

        $invitationClass = $this->invitationClass();

        if ($invitationClass::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereRaw('lower(email) = ?', [$email])
            ->whereNull('accepted_at')
            ->exists()) {
            throw ValidationException::withMessages(['email' => 'An invitation is already pending for this e-mail address.']);
        }

        /** @var WorkspaceInvitation $invitation */
        $invitation = $invitationClass::query()->create([
            'workspace_id' => $workspace->getKey(),
            'email' => $email,
            'role' => $role,
            'token' => $this->uniqueToken($invitationClass),
            'invited_by' => $inviter?->getKey(),
            'expires_at' => now()->addDays((int) config('larasaas.multi_tenant.invitation_expires_days', 7)),
        ]);

        return $invitation;
    }

    /** Accept a pending invitation: creates the membership with the invited role. */
    public function acceptInvitation(WorkspaceInvitation $invitation, $user): WorkspaceMember
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages(['invitation' => 'This invitation has expired.']);
        }

        if (config('larasaas.multi_tenant.strict_invitation_email', true)) {
            $email = strtolower((string) ($user->email ?? ''));

            if ($email === '' || $email !== strtolower((string) $invitation->email)) {
                throw new AuthorizationException('This invitation was sent to a different e-mail address.');
            }
        }

        if ($this->memberFor($invitation->workspace, $user)) {
            throw ValidationException::withMessages(['invitation' => 'You are already a member of this workspace.']);
        }

        return DB::transaction(function () use ($invitation, $user) {
            /** @var WorkspaceMember $member */
            $member = $this->membershipClass()::query()->create([
                'workspace_id' => $invitation->workspace_id,
                'user_id' => $user->getKey(),
                'role' => $invitation->role,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $member;
        });
    }

    /** Revoke a pending invitation (Owners and Admins). */
    public function revokeInvitation(Model $workspace, $actor, WorkspaceInvitation $invitation): void
    {
        if (! $this->hasRole($workspace, $actor, [WorkspaceMember::ROLE_OWNER, WorkspaceMember::ROLE_ADMIN])) {
            throw new AuthorizationException('Only workspace Owners and Admins can manage invitations.');
        }

        if ((int) $invitation->workspace_id !== (int) $workspace->getKey()) {
            throw new AuthorizationException('This invitation belongs to another workspace.');
        }

        $invitation->delete();
    }

    /** Only Owners change member roles. The last Owner can never be demoted. */
    public function changeRole(Model $workspace, $actor, $user, string $role): WorkspaceMember
    {
        if (! $this->hasRole($workspace, $actor, WorkspaceMember::ROLE_OWNER)) {
            throw new AuthorizationException('Only the workspace Owner can change roles.');
        }

        if (! in_array($role, $this->roles(), true)) {
            throw ValidationException::withMessages(['role' => "Unknown workspace role [{$role}]."]);
        }

        $member = $this->memberFor($workspace, $user)
            ?? throw ValidationException::withMessages(['user' => 'This user is not a member of the workspace.']);

        if ($member->role === WorkspaceMember::ROLE_OWNER && $role !== WorkspaceMember::ROLE_OWNER) {
            $this->guardNotLastOwner($workspace, $member);
        }

        $member->forceFill(['role' => $role])->save();

        return $member->fresh();
    }

    /**
     * Remove a member: Owners may remove anyone (except the last Owner),
     * Admins may remove plain Members.
     */
    public function removeMember(Model $workspace, $actor, $user): void
    {
        $member = $this->memberFor($workspace, $user)
            ?? throw ValidationException::withMessages(['user' => 'This user is not a member of the workspace.']);

        $actorMember = $this->memberFor($workspace, $actor);

        if ($actorMember?->isOwner()) {
            if ($member->isOwner()) {
                $this->guardNotLastOwner($workspace, $member);
            }
        } elseif ($actorMember?->hasRole(WorkspaceMember::ROLE_ADMIN)) {
            if ($member->role !== WorkspaceMember::ROLE_MEMBER) {
                throw new AuthorizationException('Admins can only remove Members.');
            }
        } else {
            throw new AuthorizationException('Only workspace Owners and Admins can remove members.');
        }

        DB::transaction(function () use ($workspace, $member) {
            $member->delete();

            // Keep the primary owner pointer valid when its user leaves.
            if ((int) $workspace->owner_id === (int) $member->user_id) {
                $successor = $workspace->members()->where('role', WorkspaceMember::ROLE_OWNER)->orderBy('id')->first();

                if ($successor) {
                    $workspace->forceFill(['owner_id' => $successor->user_id])->save();
                }
            }
        });
    }

    /* Role-rule helpers */

    /** Who may assign which roles when inviting. */
    protected function guardCanAssign(Model $workspace, $actor, string $role): void
    {
        $member = $this->memberFor($workspace, $actor);

        if (! $member) {
            throw new AuthorizationException('You are not a member of this workspace.');
        }

        $allowed = match (true) {
            $member->isOwner() => $this->roles(),
            $member->hasRole(WorkspaceMember::ROLE_ADMIN) => array_values(array_diff($this->roles(), [WorkspaceMember::ROLE_OWNER])),
            default => [],
        };

        if (! in_array($role, $allowed, true)) {
            throw new AuthorizationException('You are not allowed to assign this workspace role.');
        }
    }

    protected function guardNotLastOwner(Model $workspace, WorkspaceMember $member): void
    {
        $owners = $workspace->members()->where('role', WorkspaceMember::ROLE_OWNER)->count();

        if ($owners <= 1) {
            throw ValidationException::withMessages([
                'role' => 'A workspace must keep at least one Owner. Promote another member first.',
            ]);
        }
    }

    protected function uniqueSlug(string $workspaceClass, string $slug): string
    {
        $base = $slug !== '' ? $slug : 'workspace';
        $candidate = $base;
        $suffix = 2;

        while ($workspaceClass::query()->where('slug', $candidate)->exists()) {
            $candidate = $base . '-' . $suffix++;
        }

        return $candidate;
    }

    protected function uniqueToken(string $invitationClass): string
    {
        do {
            $token = Str::random(64);
        } while ($invitationClass::query()->where('token', $token)->exists());

        return $token;
    }

    protected function findUserByEmail(string $email): ?Model
    {
        $class = config('larasaas.user_model', 'App\\Models\\User');

        if (! class_exists($class)) {
            return null;
        }

        /** @var Model|null $user */
        $user = $class::query()->whereRaw('lower(email) = ?', [$email])->first();

        return $user;
    }
}
