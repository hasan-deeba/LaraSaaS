<?php

namespace LaraSaaS\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LaraSaaS\Models\Workspace;
use LaraSaaS\Models\WorkspaceInvitation;
use LaraSaaS\Models\WorkspaceMember;

/**
 * Validated multi-tenant workspace & team management for any host dashboard,
 * command or API route. The caller owns authorization; this service enforces
 * the structural invariants (single owner, role sanity, invitation lifecycle).
 */
class WorkspaceManager
{
    /*
    |------------------------------------------------------------------
    | Workspaces
    |------------------------------------------------------------------
    */

    /**
     * Create a workspace owned by `$owner` (the host user model) and enroll
     * the owner as the single `owner` member in the same transaction.
     */
    public function create(array $attributes, Model $owner): Workspace
    {
        $attributes = $this->validated($attributes, [
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($this->workspaceTable(), 'slug')],
        ]);

        return DB::transaction(function () use ($attributes, $owner) {
            $workspace = new ($this->workspaceModel())($attributes);
            $workspace->slug = $attributes['slug'] ?? $this->uniqueSlug($attributes['name']);
            $workspace->owner_id = $owner->getAuthIdentifier();
            $workspace->save();

            $this->membershipModel()::create([
                'workspace_id' => $workspace->getKey(),
                'user_id' => $owner->getAuthIdentifier(),
                'role' => WorkspaceRole::Owner->value,
            ]);

            return $workspace;
        });
    }

    /** Omitted fields are preserved. */
    public function update(Workspace $workspace, array $attributes): Workspace
    {
        $attributes = $this->validated($attributes, [
            'name' => ['sometimes', 'required', 'string', 'max:140'],
            'slug' => ['sometimes', 'required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique($this->workspaceTable(), 'slug')->ignore($workspace->getKey())],
        ]);

        $workspace->fill($attributes);
        $workspace->save();

        return $workspace->refresh();
    }

    /** Members and invitations cascade in the database. */
    public function delete(Workspace $workspace): void
    {
        $workspace->delete();
    }

    /*
    |------------------------------------------------------------------
    | Team management — roles
    |------------------------------------------------------------------
    */

    /**
     * Change a member's role. "owner" cannot be assigned here — use
     * transferOwnership() — and the current owner can never be demoted.
     */
    public function updateMemberRole(Workspace $workspace, Model $user, string $role): WorkspaceMember
    {
        $role = WorkspaceRole::tryFromLoose($role) ?? throw ValidationException::withMessages([
            'role' => 'The selected role is invalid. Expected one of: ' . implode(', ', array_map(
                fn (WorkspaceRole $r) => $r->value,
                WorkspaceRole::assignable(),
            )) . '.',
        ]);

        if (! in_array($role, WorkspaceRole::assignable(), true)) {
            throw ValidationException::withMessages([
                'role' => "The role [{$role->value}] cannot be assigned to members. Ownership is transferred separately.",
            ]);
        }

        $member = $this->requireMember($workspace, $user);

        if ($member->isOwner()) {
            throw ValidationException::withMessages([
                'role' => 'The workspace owner cannot be demoted. Transfer ownership first.',
            ]);
        }

        $member->role = $role->value;
        $member->save();

        return $member->refresh();
    }

    /** The owner can never be removed — transfer ownership first. */
    public function removeMember(Workspace $workspace, Model $user): void
    {
        $member = $this->requireMember($workspace, $user);

        if ($member->isOwner() || (int) $workspace->owner_id === (int) $user->getAuthIdentifier()) {
            throw ValidationException::withMessages([
                'user' => 'The workspace owner cannot be removed. Transfer ownership first.',
            ]);
        }

        $member->delete();
    }

    /**
     * Move ownership to another member (or an invited user — they are
     * enrolled first). The previous owner keeps access as an admin.
     */
    public function transferOwnership(Workspace $workspace, Model $user): Workspace
    {
        return DB::transaction(function () use ($workspace, $user) {
            $member = $workspace->members()->where('user_id', $user->getAuthIdentifier())->first()
                ?? $this->membershipModel()::create([
                    'workspace_id' => $workspace->getKey(),
                    'user_id' => $user->getAuthIdentifier(),
                    'role' => WorkspaceRole::Admin->value,
                ]);

            $previousOwner = $workspace->owner_id;

            $member->role = WorkspaceRole::Owner->value;
            $member->save();

            if ($previousOwner !== null && (int) $previousOwner !== (int) $user->getAuthIdentifier()) {
                $this->membershipModel()::query()
                    ->where('workspace_id', $workspace->getKey())
                    ->where('user_id', $previousOwner)
                    ->update(['role' => WorkspaceRole::Admin->value]);
            }

            $workspace->owner_id = $user->getAuthIdentifier();
            $workspace->save();

            return $workspace->refresh();
        });
    }

    /*
    |------------------------------------------------------------------
    | Team management — invitations
    |------------------------------------------------------------------
    */

    /**
     * Invite an e-mail with an assignable role. Re-inviting a pending e-mail
     * resends (new token + fresh expiry); an accepted invitation is cleared
     * so the e-mail can be re-invited after a member was removed.
     */
    public function invite(Workspace $workspace, string $email, string $role = 'member', ?Model $invitedBy = null): WorkspaceInvitation
    {
        $data = $this->validated(['email' => $email, 'role' => $role], [
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', 'string', 'in:' . implode(',', array_map(
                fn (WorkspaceRole $r) => $r->value,
                WorkspaceRole::assignable(),
            ))],
        ]);

        $email = strtolower($data['email']);

        if ($workspace->members()->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [$email]))->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This person is already a member of the workspace.',
            ]);
        }

        $invitation = $this->invitationModel()::query()
            ->where('workspace_id', $workspace->getKey())
            ->whereRaw('lower(email) = ?', [$email])
            ->first();

        if ($invitation === null) {
            $invitation = new ($this->invitationModel())([
                'workspace_id' => $workspace->getKey(),
                'email' => $email,
            ]);
        }

        $invitation->role = $data['role'];
        $invitation->token = Str::random(64);
        $invitation->invited_by = $invitedBy?->getAuthIdentifier();
        $invitation->expires_at = now()->addDays(max(1, (int) config('larasaas.workspaces.invitation_ttl_days', 7)));
        $invitation->accepted_at = null;
        $invitation->save();

        return $invitation->refresh();
    }

    /** Accept by token for `$user` (e-mail must match when configured). */
    public function acceptByToken(string $token, Model $user): WorkspaceMember
    {
        $invitation = $this->invitationModel()::query()->where('token', $token)->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'token' => 'This invitation link is invalid.',
            ]);
        }

        return $this->accept($invitation, $user);
    }

    public function accept(WorkspaceInvitation $invitation, Model $user): WorkspaceMember
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has already been accepted.',
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has expired. Ask for a new one.',
            ]);
        }

        if (config('larasaas.workspaces.invite_by_email', true)) {
            $email = (string) ($user->email ?? '');

            if ($email === '' || strtolower($email) !== strtolower((string) $invitation->email)) {
                throw ValidationException::withMessages([
                    'email' => 'This invitation was sent to a different e-mail address.',
                ]);
            }
        }

        return DB::transaction(function () use ($invitation, $user) {
            $member = $invitation->workspace->members()
                ->where('user_id', $user->getAuthIdentifier())
                ->first();

            if ($member === null) {
                $member = $this->membershipModel()::create([
                    'workspace_id' => $invitation->workspace_id,
                    'user_id' => $user->getAuthIdentifier(),
                    'role' => $invitation->roleEnum()->value,
                ]);
            }

            $invitation->accepted_at = now();
            $invitation->save();

            return $member->refresh();
        });
    }

    /** Revoke a pending invitation. */
    public function cancelInvitation(WorkspaceInvitation $invitation): void
    {
        $invitation->delete();
    }

    /** Regenerate the token + expiry of a pending invitation (resend). */
    public function resendInvitation(WorkspaceInvitation $invitation): WorkspaceInvitation
    {
        if ($invitation->isAccepted()) {
            throw ValidationException::withMessages([
                'token' => 'This invitation has already been accepted.',
            ]);
        }

        $invitation->token = Str::random(64);
        $invitation->expires_at = now()->addDays(max(1, (int) config('larasaas.workspaces.invitation_ttl_days', 7)));
        $invitation->save();

        return $invitation->refresh();
    }

    /*
    |------------------------------------------------------------------
    | Helpers
    |------------------------------------------------------------------
    */

    /** @return class-string<Workspace> */
    public function workspaceModel(): string
    {
        return config('larasaas.workspaces.model', Workspace::class);
    }

    public function workspaceTable(): string
    {
        return (new ($this->workspaceModel()))->getTable();
    }

    /** @return class-string<WorkspaceMember> */
    public function membershipModel(): string
    {
        return config('larasaas.workspaces.member_model', WorkspaceMember::class);
    }

    /** @return class-string<WorkspaceInvitation> */
    public function invitationModel(): string
    {
        return config('larasaas.workspaces.invitation_model', WorkspaceInvitation::class);
    }

    protected function requireMember(Workspace $workspace, Model $user): WorkspaceMember
    {
        return $workspace->members()->where('user_id', $user->getAuthIdentifier())->first()
            ?? throw ValidationException::withMessages([
                'user' => 'This user is not a member of the workspace.',
            ]);
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;

        for ($i = 2; $this->slugExists($slug); $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
    }

    protected function slugExists(string $slug): bool
    {
        return $this->workspaceModel()::query()->where('slug', $slug)->exists();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     */
    protected function validated(array $attributes, array $rules): array
    {
        return Validator::make($attributes, $rules)->validate();
    }
}
