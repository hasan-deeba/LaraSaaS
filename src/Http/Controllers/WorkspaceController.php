<?php

namespace LaraSaaS\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use LaraSaaS\Http\Controllers\Concerns\ResolvesWorkspaces;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Support\WorkspaceManager;
use LaraSaaS\Support\WorkspacePresenter;

/**
 * Multi-tenant workspaces & team management for the Inertia surface:
 * parallel workspaces, granular roles (Owner, Admin, Member) and
 * invitations. All endpoints are only registered when
 * `larasaas.multi_tenant.enabled` is true.
 */
class WorkspaceController extends Controller
{
    use ResolvesWorkspaces;

    public function __construct(
        protected LaraSaaS $larasaas,
        protected WorkspaceManager $manager,
        protected WorkspacePresenter $presenter,
    ) {}

    /** GET /app/workspaces — every workspace the user belongs to. */
    public function index(Request $request)
    {
        $user = $request->user();

        return Inertia::render($this->larasaas->page('Workspaces'), [
            'workspaces' => $this->manager->workspacesFor($user)
                ->map(fn ($workspace) => $this->presenter->summary($workspace, $user))
                ->values()
                ->all(),
            'roles' => $this->larasaas->roleDefinitions(),
            'store_url' => route($this->larasaas->routeName('workspaces.store')),
            'team_url_base' => '/' . trim(config('larasaas.route.prefix', 'app'), '/') . '/workspaces',
        ]);
    }

    /** POST /app/workspaces — create a workspace (creator becomes Owner). */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:140'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ]);

        $workspace = $this->larasaas->createWorkspace($request->user(), $request->only(['name', 'slug']));

        return redirect()->route($this->larasaas->routeName('workspaces.show'), ['workspace' => $workspace->getKey()]);
    }

    /** GET /app/workspaces/{workspace} — the team management page. */
    public function show(Request $request, string $workspace)
    {
        $workspace = $this->findWorkspace($workspace);
        $user = $request->user();

        $this->authorizeMember($workspace, $user);

        $canManage = $this->manager->hasRole($workspace, $user, ['owner', 'admin']);

        return Inertia::render($this->larasaas->page('Team'), [
            'workspace' => $this->presenter->summary($workspace, $user),
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
            'roles' => $this->larasaas->roleDefinitions(),
            'urls' => [
                'invite' => route($this->larasaas->routeName('workspaces.invitations.store'), ['workspace' => $workspace->getKey()]),
                'revoke' => route($this->larasaas->routeName('workspaces.invitations.destroy'), ['workspace' => $workspace->getKey(), 'invitation' => '__invitation__']),
                'role' => route($this->larasaas->routeName('workspaces.members.role'), ['workspace' => $workspace->getKey(), 'user' => '__user__']),
                'remove' => route($this->larasaas->routeName('workspaces.members.destroy'), ['workspace' => $workspace->getKey(), 'user' => '__user__']),
                'back' => route($this->larasaas->routeName('workspaces.index')),
            ],
        ]);
    }

    /** POST — invite an e-mail address with a granular role. */
    public function invite(Request $request, string $workspace)
    {
        $workspace = $this->findWorkspace($workspace);

        $request->validate([
            'email' => ['required', 'email', 'max:190'],
            'role'  => ['nullable', 'string', 'in:' . implode(',', array_keys($this->larasaas->roleDefinitions()))],
        ]);

        $this->larasaas->inviteMember($workspace, $request->user(), $request->input('email'), $request->input('role'));

        return back();
    }

    /** DELETE — revoke a pending invitation. */
    public function revokeInvitation(Request $request, string $workspace, string $invitation)
    {
        $workspace = $this->findWorkspace($workspace);
        $invitation = $this->findInvitation($invitation);

        $this->larasaas->revokeInvitation($workspace, $request->user(), $invitation);

        return back();
    }

    /** PUT — change a member's role (Owner only). */
    public function changeRole(Request $request, string $workspace, string $user)
    {
        $workspace = $this->findWorkspace($workspace);
        $target = $this->findWorkspaceUser($user);

        $request->validate([
            'role' => ['required', 'string', 'in:' . implode(',', array_keys($this->larasaas->roleDefinitions()))],
        ]);

        $this->larasaas->changeMemberRole($workspace, $request->user(), $target, $request->input('role'));

        return back();
    }

    /** DELETE — remove a member from the workspace. */
    public function removeMember(Request $request, string $workspace, string $user)
    {
        $workspace = $this->findWorkspace($workspace);
        $target = $this->findWorkspaceUser($user);

        $this->larasaas->removeMember($workspace, $request->user(), $target);

        return back();
    }

    /** POST /app/invitations/{token}/accept — join the workspace. */
    public function acceptInvitation(Request $request, string $token)
    {
        $invitation = $this->larasaas->acceptInvitation($token, $request->user());

        return redirect()->route($this->larasaas->routeName('workspaces.show'), ['workspace' => $invitation->workspace_id]);
    }
}
