<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\LaraSaaS;
use Laravel\Cashier\Billable;

class WorkspacesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', TeamUser::class);
        $app['config']->set('auth.providers.users.model', TeamUser::class);
        $app['config']->set('larasaas.multi_tenant.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email');
            $table->timestamps();
        });

        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
        (require __DIR__ . '/../database/migrations/2026_10_14_000001_create_larasaas_workspaces_schema.php')->up();
    }

    private function user(string $email = 'owner@example.com'): TeamUser
    {
        return TeamUser::create(['email' => $email, 'name' => ucfirst(strtok($email, '@'))]);
    }

    private function workspaceFor(TeamUser $owner, string $name = 'Acme'): \LaraSaaS\Models\Workspace
    {
        return app(LaraSaaS::class)->createWorkspace($owner, ['name' => $name]);
    }

    /* Workspaces */

    public function test_creating_a_workspace_makes_the_creator_its_owner(): void
    {
        $owner = $this->user();
        $this->actingAs($owner);

        $this->postJson('/api/larasaas/workspaces', ['name' => 'Acme Inc'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Acme Inc')
            ->assertJsonPath('data.slug', 'acme-inc')
            ->assertJsonPath('data.role', 'owner');

        $workspace = \LaraSaaS\Models\Workspace::firstOrFail();
        $this->assertSame($owner->id, (int) $workspace->owner_id);
        $member = \LaraSaaS\Models\WorkspaceMember::firstOrFail();
        $this->assertSame('owner', $member->role);
        $this->assertSame($owner->id, (int) $member->user_id);
    }

    public function test_users_only_see_their_own_workspaces(): void
    {
        $owner = $this->user();
        $this->workspaceFor($owner, 'Mine');
        $other = $this->user('other@example.com');
        $this->workspaceFor($other, 'Theirs');

        $this->actingAs($owner)->getJson('/api/larasaas/workspaces')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Mine');

        $this->actingAs($other)->getJson('/api/larasaas/workspaces/' . \LaraSaaS\Models\Workspace::where('name', 'Mine')->first()->id)
            ->assertForbidden();
    }

    /* Invitations */

    public function test_invitation_flow_creates_members_with_the_invited_role(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);

        $this->actingAs($owner)
            ->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'joiner@example.com', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'joiner@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.pending', true);

        $token = \LaraSaaS\Models\WorkspaceInvitation::firstOrFail()->token;

        // Anyone can preview the invitation before accepting.
        $this->actingAs($this->user('joiner@example.com'))
            ->getJson("/api/larasaas/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.workspace.name', 'Acme');

        $this->actingAs($this->user('joiner@example.com'))
            ->postJson("/api/larasaas/invitations/{$token}/accept")
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('larasaas_workspace_members', [
            'workspace_id' => $workspace->id,
            'user_id' => \LaraSaaS\Models\WorkspaceMember::where('role', 'admin')->first()->user_id,
            'role' => 'admin',
        ]);
        $this->assertTrue((bool) \LaraSaaS\Models\WorkspaceInvitation::first()->accepted_at);
    }

    public function test_invitations_are_email_strict_expire_and_reject_duplicates(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);

        // Duplicate pending invitations are rejected.
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'dup@example.com'])->assertCreated();
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'dup@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        // Inviting an existing member is rejected.
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'owner@example.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        // Strict e-mail matching: only the invited address can accept.
        $token = \LaraSaaS\Models\WorkspaceInvitation::where('email', 'dup@example.com')->first()->token;
        $this->actingAs($this->user('intruder@example.com'))->postJson("/api/larasaas/invitations/{$token}/accept")->assertForbidden();

        // Expired invitations cannot be accepted.
        $this->travel(8)->days();
        $this->actingAs($this->user('dup@example.com'))->postJson("/api/larasaas/invitations/{$token}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitation');
    }

    public function test_revoking_an_invitation_blocks_acceptance(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'gone@example.com'])->assertCreated();
        $invitation = \LaraSaaS\Models\WorkspaceInvitation::firstOrFail();

        $this->actingAs($owner)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/invitations/{$invitation->id}")->assertOk();
        $this->assertDatabaseMissing('larasaas_workspace_invitations', ['id' => $invitation->id]);

        $this->actingAs($this->user('gone@example.com'))->postJson("/api/larasaas/invitations/{$invitation->token}/accept")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('invitation');
    }

    /* Granular roles */

    public function test_role_rules_are_granular_across_owner_admin_and_member(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);

        $admin = $this->user('admin@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'admin@example.com', 'role' => 'admin'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::first()->token . '/accept')->assertCreated();

        $member = $this->user('member@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'member@example.com', 'role' => 'member'])->assertCreated();
        $this->actingAs($member)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::where('email', 'member@example.com')->first()->token . '/accept')->assertCreated();

        // Members cannot invite at all.
        $this->actingAs($member)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'x@example.com'])->assertForbidden();

        // Admins can invite Admins and Members, but never Owners.
        $this->actingAs($admin)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'admin2@example.com', 'role' => 'admin'])->assertCreated();
        $this->actingAs($admin)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'o@example.com', 'role' => 'owner'])->assertForbidden();

        // Members cannot see the invitation list; Admins can.
        $this->actingAs($member)->getJson("/api/larasaas/workspaces/{$workspace->id}/invitations")->assertForbidden();
        $this->actingAs($admin)->getJson("/api/larasaas/workspaces/{$workspace->id}/invitations")->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_only_owners_change_roles_and_the_last_owner_is_protected(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);
        $member = $this->user('member@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'member@example.com'])->assertCreated();
        $this->actingAs($member)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::first()->token . '/accept')->assertCreated();

        // Admins (and members) cannot change roles — only Owners.
        $admin = $this->user('admin@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'admin@example.com', 'role' => 'admin'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::where('email', 'admin@example.com')->first()->token . '/accept')->assertCreated();
        $this->actingAs($admin)->putJson("/api/larasaas/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'admin'])->assertForbidden();

        // The last Owner can never be demoted…
        $this->actingAs($owner)->putJson("/api/larasaas/workspaces/{$workspace->id}/members/{$owner->id}/role", ['role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');

        // …until a second Owner exists.
        $this->actingAs($owner)->putJson("/api/larasaas/workspaces/{$workspace->id}/members/{$member->id}/role", ['role' => 'owner'])->assertOk()
            ->assertJsonPath('data.role', 'owner');
        $this->actingAs($owner)->putJson("/api/larasaas/workspaces/{$workspace->id}/members/{$owner->id}/role", ['role' => 'member'])->assertOk();
        $this->assertSame('member', app(LaraSaaS::class)->workspaceMembers($workspace)->firstWhere('user_id', $owner->id)->role);
    }

    public function test_member_removal_rules_and_owner_pointer_reassignment(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);
        $member = $this->user('member@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'member@example.com'])->assertCreated();
        $this->actingAs($member)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::first()->token . '/accept')->assertCreated();
        $admin = $this->user('admin@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'admin@example.com', 'role' => 'admin'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::where('email', 'admin@example.com')->first()->token . '/accept')->assertCreated();

        // Admins can remove Members but neither Owners nor other Admins.
        $this->actingAs($admin)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/members/{$owner->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/members/{$admin->id}")->assertForbidden();
        $this->actingAs($admin)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/members/{$member->id}")->assertOk();
        $this->assertDatabaseMissing('larasaas_workspace_members', ['workspace_id' => $workspace->id, 'user_id' => $member->id]);

        // The last Owner cannot leave the workspace ownerless.
        $this->actingAs($owner)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/members/{$owner->id}")
            ->assertUnprocessable();

        // With a co-owner, the primary owner pointer follows the successor.
        $co = $this->user('co@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'co@example.com', 'role' => 'owner'])->assertCreated();
        $this->actingAs($co)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::where('email', 'co@example.com')->first()->token . '/accept')->assertCreated();
        $this->actingAs($owner)->deleteJson("/api/larasaas/workspaces/{$workspace->id}/members/{$owner->id}")->assertOk();
        $this->assertSame($co->id, (int) $workspace->fresh()->owner_id);
    }

    /* Role model */

    public function test_roles_imply_their_lower_roles(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);
        $member = app(LaraSaaS::class)->workspaceMembers($workspace)->first();

        $this->assertTrue($member->hasRole('owner'));
        $this->assertTrue($member->hasRole('admin'));
        $this->assertTrue($member->hasRole('member'));
        $this->assertTrue($member->hasRole(['admin', 'member']));
        $this->assertFalse($member->hasRole('guest'));
    }

    /* Billing integration */

    public function test_multi_tenant_workspace_billing_resolves_the_users_current_workspace(): void
    {
        config([
            'larasaas.multi_tenant.workspace_model' => BillableTeamWorkspace::class,
            'larasaas.billing.billable' => 'workspace',
        ]);

        $owner = $this->user();
        /** @var BillableTeamWorkspace $first */
        $first = app(LaraSaaS::class)->createWorkspace($owner, ['name' => 'First']);
        /** @var BillableTeamWorkspace $second */
        $second = app(LaraSaaS::class)->createWorkspace($owner, ['name' => 'Second']);

        $request = Request::create('/');
        $request->setUserResolver(fn () => $owner);

        // No custom resolver and no currentWorkspace() method: the multi-tenant
        // tables drive the default — most recently joined workspace.
        $this->assertSame($second->getKey(), app(LaraSaaS::class)->billable($request)->getKey());

        // Membership order decides; joining the first workspace again is not
        // possible, so removing the second makes the first current again.
        $second->members()->where('user_id', $owner->id)->delete();
        $this->assertSame($first->getKey(), app(LaraSaaS::class)->billable($request)->getKey());
    }

    /* Inertia surface */

    public function test_workspace_and_team_pages_render_with_roles(): void
    {
        $owner = $this->user();
        $workspace = $this->workspaceFor($owner);
        $this->actingAs($owner);

        $this->get('/app/workspaces', ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'LaraSaaS/Workspaces')
            ->assertJsonPath('props.workspaces.0.name', 'Acme');

        $this->get("/app/workspaces/{$workspace->id}", ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('component', 'LaraSaaS/Team')
            ->assertJsonPath('props.workspace.role', 'owner')
            ->assertJsonPath('props.is_owner', true)
            ->assertJsonPath('props.members.0.role', 'owner');

        // A member without management rights never receives the invite URLs data.
        $member = $this->user('member@example.com');
        $this->actingAs($owner)->postJson("/api/larasaas/workspaces/{$workspace->id}/invitations", ['email' => 'member@example.com'])->assertCreated();
        $this->actingAs($member)->postJson('/api/larasaas/invitations/' . \LaraSaaS\Models\WorkspaceInvitation::first()->token . '/accept')->assertCreated();
        $this->actingAs($member)->get("/app/workspaces/{$workspace->id}", ['X-Inertia' => 'true'])
            ->assertOk()
            ->assertJsonPath('props.can_manage', false)
            ->assertJsonPath('props.invitations', []);
    }
}

class TeamUser extends User
{
    protected $table = 'users';
    protected $guarded = [];
}

class BillableTeamWorkspace extends \LaraSaaS\Models\Workspace
{
    use Billable;
}
