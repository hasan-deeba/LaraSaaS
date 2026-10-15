<?php

namespace LaraSaaS\Http\Controllers\Api;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Workspace;
use LaraSaaS\Models\WorkspaceInvitation;
use LaraSaaS\Support\WorkspaceManager;

/**
 * Multi-tenant workspaces & team management (`api` / `inertia` / `embed`
 * modes). Every response uses the package's `{"data": …}` envelope; role-based
 * authorization rides on `larasaas.workspace.*` middleware, while the
 * WorkspaceManager enforces the structural invariants (single owner, role
 * sanity, invitation lifecycle).
 */
class WorkspaceApiController extends Controller
{
    public function __construct(protected WorkspaceManager $workspaces) {}

    /** Workspaces the authenticated user belongs to. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $this->workspaces->workspaceModel()::query()
            ->visibleTo($user)
            ->orderBy($this->workspaces->workspaceTable() . '.id')
            ->get()
            ->map(function (Workspace $workspace) use ($user) {
                $role = $workspace->roleFor($user);

                return array_merge($workspace->toUiArray(), [
                    'role' => $role?->value,
                    'role_label' => $role?->label(),
                    'permissions' => $role?->permissions() ?? [],
                ]);
            })
            ->all();

        return response()->json(['data' => $data]);
    }

    /** Create a workspace — the caller becomes its Owner. */
    public function store(Request $request): JsonResponse
    {
        $workspace = $this->workspaces->create($request->all(), $request->user());

        return response()->json([
            'data' => array_merge($workspace->fresh()->toUiArray(), [
                'role' => 'owner',
                'role_label' => 'Owner',
            ]),
        ], 201);
    }

    /** Workspace + team roster (+ pending invitations for those who may see them). */
    public function show(Request $request, Workspace $workspace): JsonResponse
    {
        $role = $workspace->roleFor($request->user());

        $data = array_merge($workspace->toUiArray(), [
            'role' => $role?->value,
            'role_label' => $role?->label(),
            'permissions' => $role?->permissions() ?? [],
            'members' => $workspace->members()->orderBy('id')->get()->map->toUiArray()->all(),
        ]);

        if ($role?->allows('invitations.view')) {
            $data['invitations'] = $workspace->invitations()->orderBy('id')->get()->map->toUiArray()->all();
        }

        return response()->json(['data' => $data]);
    }

    /** Rename the workspace / change its slug (permission: workspace.update). */
    public function update(Request $request, Workspace $workspace): JsonResponse
    {
        $workspace = $this->workspaces->update($workspace, $request->all());

        return response()->json(['data' => $workspace->toUiArray()]);
    }

    /** Delete the workspace and its team (permission: workspace.delete — Owner). */
    public function destroy(Workspace $workspace): JsonResponse
    {
        $this->workspaces->delete($workspace);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /*
    |----------------------------------------------------------------------
    | Team management
    |----------------------------------------------------------------------
    */

    /** Invite an e-mail with a granular role (permission: members.invite). */
    public function invite(Request $request, Workspace $workspace): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role' => ['nullable', 'string'],
        ]);

        $invitation = $this->workspaces->invite(
            $workspace,
            (string) $request->input('email'),
            (string) ($request->input('role') ?: 'member'),
            $request->user(),
        );

        return response()->json(['data' => $invitation->toUiArray()], 201);
    }

    /** Pending invitations (permission: invitations.view). */
    public function invitations(Workspace $workspace): JsonResponse
    {
        return response()->json([
            'data' => $workspace->invitations()->orderBy('id')->get()->map->toUiArray()->all(),
        ]);
    }

    /** Revoke a pending invitation (permission: invitations.manage). */
    public function revokeInvitation(Workspace $workspace, WorkspaceInvitation $invitation): JsonResponse
    {
        abort_unless((int) $invitation->workspace_id === (int) $workspace->getKey(), 404);

        $this->workspaces->cancelInvitation($invitation);

        return response()->json(['data' => ['revoked' => true]]);
    }

    /** Accept an invitation by token — the signed-in user joins the team. */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $member = $this->workspaces->acceptByToken($token, $request->user());

        return response()->json(['data' => $member->toUiArray()]);
    }

    /** Change a member's role (permission: members.update). Owner is transfer-only. */
    public function updateMemberRole(Request $request, Workspace $workspace, string $member): JsonResponse
    {
        $request->validate(['role' => ['required', 'string']]);

        $membership = $this->workspaces->updateMemberRole(
            $workspace,
            $this->resolveMember($workspace, $member),
            (string) $request->input('role'),
        );

        return response()->json(['data' => $membership->toUiArray()]);
    }

    /** Remove a member (permission: members.remove). The Owner can never be removed. */
    public function removeMember(Workspace $workspace, string $member): JsonResponse
    {
        $this->workspaces->removeMember($workspace, $this->resolveMember($workspace, $member));

        return response()->json(['data' => ['removed' => true]]);
    }

    /** Hand the workspace to another member (permission: ownership.transfer). */
    public function transferOwnership(Request $request, Workspace $workspace): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer']]);

        $workspace = $this->workspaces->transferOwnership(
            $workspace,
            $this->resolveMember($workspace, (string) $request->input('user_id')),
        );

        return response()->json(['data' => $workspace->toUiArray()]);
    }

    /** The member route parameter is the host user id of the member. */
    protected function resolveMember(Workspace $workspace, string $userId): Model
    {
        $user = app(LaraSaaS::class)->newUserModel()->newQuery()->find($userId);

        abort_unless($user !== null && $workspace->hasMember($user), 404);

        return $user;
    }
}
