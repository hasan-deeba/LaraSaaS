<?php

namespace LaraSaaS\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Shapes workspace/team data for the Inertia pages and the JSON API —
 * the single format every integration surface consumes.
 */
class WorkspacePresenter
{
    public function summary(Model $workspace, ?Model $user = null): array
    {
        $role = $user ? app(WorkspaceManager::class)->memberFor($workspace, $user)?->role : null;

        return [
            'id'         => $workspace->getKey(),
            'name'       => $workspace->name,
            'slug'       => $workspace->slug,
            'owner_id'   => $workspace->owner_id,
            'role'       => $role,
            'created_at' => $workspace->created_at?->toIso8601String(),
        ];
    }

    public function member(Model $member): array
    {
        $user = $member->user;

        return [
            'user_id'    => $member->user_id,
            'role'       => $member->role,
            'email'      => $user?->email,
            'name'       => $user?->name ?? $user?->email ?? null,
            'joined_at' => $member->created_at?->toIso8601String(),
        ];
    }

    public function invitation(Model $invitation): array
    {
        return [
            'id'          => $invitation->getKey(),
            'email'       => $invitation->email,
            'role'        => $invitation->role,
            'token'       => $invitation->token,
            'expires_at'  => $invitation->expires_at?->toIso8601String(),
            'accepted_at' => $invitation->accepted_at?->toIso8601String(),
            'pending'     => $invitation->isPending(),
        ];
    }
}
