<?php

namespace LaraSaaS\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LaraSaaS\Http\Controllers\Concerns\ResolvesWorkspaces;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\WorkspaceManager;
use LaraSaaS\Support\WorkspacePresenter;

/**
 * Headless workspace & team surface — parallel workspaces, granular
 * workspace-level roles (Owner, Admin, Member) and the invitation system,
 * for React SPAs on other servers, Flutter/native apps, or any client.
 * Responses use the standard `{"data": …}` envelope.
 */
class WorkspaceApiController extends Controller
{
    use ResolvesWorkspaces;

    public function __construct(
        protected LaraSaaS $larasaas,
        protected WorkspaceManager $manager,
        protected WorkspacePresenter $presenter,
    ) {}

    /** GET — every workspace the authenticated user belongs to. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->manager->workspacesFor($user)
                ->map(fn ($workspace) => $this->presenter->summary($workspace, $user))
                ->values()
                ->all(),
        ]);
    }

    /** POST — create a workspace (creator becomes Owner). Body: name, slug? */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $workspace = $this->larasaas->createWorkspace($request->user(), $request->only(['name', 'slug']));

        return response()->json([
            'data' => $this->presenter->summary($workspace, $request->user()),
        ], 201);
    }

    /** GET — workspace detail with members (and invitations for managers). */
    public function show(Request $request, string $workspace): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);
        $user = $request->user();

        $this->authorizeMember($workspace, $user);

        $canManage = $this->manager->hasRole($workspace, $user, ['owner', 'admin']);

        return response()->json([
            'data' => array_merge($this->presenter->summary($workspace, $user), [
                'members' => $this->manager->members($workspace)
                    ->map(fn ($member) => $this->presenter->member($member))
                    ->values()
                    ->all(),
                'invitations' => $canManage
                    ? $this->manager->invitations($workspace)
                        ->map(fn ($invitation) => $this->presenter->invitation($invitation))
                        ->values()
                        ->all()
                    : [],
                'can_manage' => $canManage,
                'is_owner' => $this->manager->hasRole($workspace, $user, 'owner'),
            ]),
        ]);
    }

    /** GET — workspace members with their granular roles. */
    public function members(Request $request, string $workspace): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);

        $this->authorizeMember($workspace, $request->user());

        return response()->json([
            'data' => $this->manager->members($workspace)
                ->map(fn ($member) => $this->presenter->member($member))
                ->values()
                ->all(),
        ]);
    }

    /** POST — invite an e-mail address. Body: email, role? (owner|admin|member) */
    public function invite(Request $request, string $workspace): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);

        $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role'  => ['nullable', 'string', 'in:' . implode(',', array_keys($this->larasaas->roleDefinitions()))],
        ]);

        $invitation = $this->larasaas->inviteMember($workspace, $request->user(), $request->input('email'), $request->input('role'));

        return response()->json([
            'data' => $this->presenter->invitation($invitation),
        ], 201);
    }

    /** GET — pending/known invitations (Owners and Admins). */
    public function invitations(Request $request, string $workspace): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);

        abort_unless($this->manager->hasRole($workspace, $request->user(), ['owner', 'admin']), 403);

        return response()->json([
            'data' => $this->manager->invitations($workspace)
                ->map(fn ($invitation) => $this->presenter->invitation($invitation))
                ->values()
                ->all(),
        ]);
    }

    /** DELETE — revoke a pending invitation. */
    public function revokeInvitation(Request $request, string $workspace, string $invitation): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);
        $invitation = $this->findInvitation($invitation);

        $this->larasaas->revokeInvitation($workspace, $request->user(), $invitation);

        return response()->json(['data' => ['revoked' => true]]);
    }

    /** PUT — change a member's role (Owner only). Body: role */
    public function changeRole(Request $request, string $workspace, string $user): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);
        $target = $this->findWorkspaceUser($user);

        $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', array_keys($this->larasaas->roleDefinitions()))],
        ]);

        $member = $this->larasaas->changeMemberRole($workspace, $request->user(), $target, $request->input('role'));

        return response()->json([
            'data' => $this->presenter->member($member),
        ]);
    }

    /** DELETE — remove a member from the workspace. */
    public function removeMember(Request $request, string $workspace, string $user): JsonResponse
    {
        $workspace = $this->findWorkspace($workspace);
        $target = $this->findWorkspaceUser($user);

        $this->larasaas->removeMember($workspace, $request->user(), $target);

        return response()->json(['data' => ['removed' => true]]);
    }

    /** GET — preview an invitation by its token. */
    public function showInvitation(Request $request, string $token): JsonResponse
    {
        $invitation = app(LaraSaaS::class)->invitationModel()::query()->where('token', $token)->first();
        abort_unless($invitation, 404);

        return response()->json([
            'data' => array_merge($this->presenter->invitation($invitation), [
                'workspace' => $this->presenter->summary($invitation->workspace),
            ]),
        ]);
    }

    /** POST — accept an invitation and join the workspace. */
    public function acceptInvitation(Request $request, string $token): JsonResponse
    {
        $member = $this->larasaas->acceptInvitation($token, $request->user());

        return response()->json([
            'data' => $this->presenter->member($member),
        ], 201);
    }
}
