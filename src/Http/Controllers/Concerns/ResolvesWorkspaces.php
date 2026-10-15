<?php

namespace LaraSaaS\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\WorkspaceManager;

/**
 * Resolves route parameters against the CONFIGURED workspace/user/invitation
 * models — never hardcoded classes, so host-swapped models keep working.
 */
trait ResolvesWorkspaces
{
    protected function findWorkspace(string|int $id): Model
    {
        /** @var Model|null $workspace */
        $workspace = app(LaraSaaS::class)->workspaceModel()::query()->find($id);
        abort_unless($workspace, 404);

        return $workspace;
    }

    protected function findWorkspaceUser(string|int $id): Model
    {
        /** @var Model|null $user */
        $user = app(LaraSaaS::class)->usersQuery()->find($id);
        abort_unless($user, 404);

        return $user;
    }

    protected function findInvitation(string|int $id): Model
    {
        /** @var Model|null $invitation */
        $invitation = app(LaraSaaS::class)->invitationModel()::query()->find($id);
        abort_unless($invitation, 404);

        return $invitation;
    }

    /** Workspace data is members-only. */
    protected function authorizeMember(Model $workspace, $user): void
    {
        abort_unless(app(WorkspaceManager::class)->memberFor($workspace, $user), 403);
    }
}
