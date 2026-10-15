<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HTTP surface of the multi-tenant workspaces & team management: access
 * control, granular role permissions and the invitation lifecycle.
 */
class WorkspaceApiTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('larasaas.integration.mode', 'api');
    }

    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });
    }

    private function user(string $email): \App\Models\User
    {
        return \App\Models\User::create(['email' => $email]);
    }

    /** Build a workspace with an owner, an admin and a member. */
    private function team(): array
    {
        $owner = $this->user('owner@example.com');
        $admin = $this->user('admin@example.com');
        $member = $this->user('member@example.com');

        $workspace = app(\LaraSaaS\Support\WorkspaceManager::class)->create(['name' => 'Acme'], $owner);

        foreach ([['admin@example.com', 'admin', $admin], ['member@example.com', 'member', $member]] as [$email, $role, $user]) {
            $invitation = app(\LaraSaaS\Support\WorkspaceManager::class)->invite($workspace, $email, $role, $owner);
            app(\LaraSaaS\Support\WorkspaceManager::class)->acceptByToken($invitation->token, $user);
        }

        return [$workspace, $owner, $admin, $member];
    }

    private function inviteUrl($workspace, string $suffix = ''): string
    {
        return '/api/larasaas/workspaces/' . $workspace->id . $suffix;
    }

    /*
    |----------------------------------------------------------------------
    | Access
    |----------------------------------------------------------------------
    */

    public function test_workspaces_endpoints_require_authentication(): void
    {
        $this->getJson('/api/larasaas/workspaces')->assertUnauthorized();
        $this->postJson('/api/larasaas/workspaces', ['name' => 'X'])->assertUnauthorized();
        $this->postJson('/api/larasaas/workspaces/invitations/token/accept')->assertUnauthorized();
    }

    public function test_only_members_can_see_a_workspace(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();
        $outsider = $this->user('outsider@example.com');

        $this->actingAs($outsider)->getJson($this->inviteUrl($workspace))->assertForbidden();
        $this->actingAs($member)->getJson($this->inviteUrl($workspace))->assertOk()
            ->assertJsonPath('data.role', 'member')
            ->assertJsonMissingPath('data.invitations');
        $this->actingAs($admin)->getJson($this->inviteUrl($workspace))->assertOk()
            ->assertJsonPath('data.invitations.0.email', 'admin@example.com')
            ->assertJsonPath('data.invitations.0.pending', false);
    }

    public function test_index_lists_only_my_workspaces(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();
        $this->managerCreateOtherWorkspace($owner);

        $this->actingAs($member)->getJson('/api/larasaas/workspaces')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $workspace->id);

        $this->actingAs($owner)->getJson('/api/larasaas/workspaces')->assertOk()->assertJsonCount(2, 'data');
    }

    private function managerCreateOtherWorkspace($owner): void
    {
        app(\LaraSaaS\Support\WorkspaceManager::class)->create(['name' => 'Second'], $owner);
    }

    public function test_any_authenticated_user_can_create_a_workspace_and_becomes_owner(): void
    {
        $user = $this->user('new@example.com');

        $this->actingAs($user)->postJson('/api/larasaas/workspaces', ['name' => 'Fresh Co'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Fresh Co')
            ->assertJsonPath('data.role', 'owner')
            ->assertJsonPath('data.slug', 'fresh-co');
    }

    /*
    |----------------------------------------------------------------------
    | Team management
    |----------------------------------------------------------------------
    */

    public function test_inviting_requires_the_members_invite_permission(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();

        $this->actingAs($member)->postJson($this->inviteUrl($workspace, '/invitations'), ['email' => 'x@example.com'])
            ->assertForbidden();

        $this->actingAs($admin)->postJson($this->inviteUrl($workspace, '/invitations'), ['email' => 'x@example.com', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'x@example.com')
            ->assertJsonPath('data.role', 'admin');

        $this->actingAs($admin)->postJson($this->inviteUrl($workspace, '/invitations'), ['email' => 'bad'])
            ->assertUnprocessable();
        $this->actingAs($admin)->postJson($this->inviteUrl($workspace, '/invitations'), ['email' => 'y@example.com', 'role' => 'owner'])
            ->assertUnprocessable();
    }

    public function test_accepting_an_invitation_over_the_api(): void
    {
        [$workspace, $owner] = $this->team();
        $invitation = app(\LaraSaaS\Support\WorkspaceManager::class)->invite($workspace, 'joiner@example.com', 'member', $owner);

        $this->actingAs($this->user('someone@example.com'))
            ->postJson('/api/larasaas/workspaces/invitations/' . $invitation->token . '/accept')
            ->assertUnprocessable(); // e-mail mismatch

        $joiner = $this->user('joiner@example.com');
        $this->actingAs($joiner)
            ->postJson('/api/larasaas/workspaces/invitations/' . $invitation->token . '/accept')
            ->assertOk()
            ->assertJsonPath('data.role', 'member');

        $this->assertTrue($workspace->hasMember($joiner));
    }

    public function test_role_changes_are_gated_and_ownership_is_protected(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();

        // Members cannot change roles.
        $this->actingAs($member)->patchJson($this->inviteUrl($workspace, '/members/' . $member->id), ['role' => 'admin'])
            ->assertForbidden();

        // Admins can promote/demote other non-owners.
        $this->actingAs($admin)->patchJson($this->inviteUrl($workspace, '/members/' . $member->id), ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin');

        // Nobody demotes the owner; "owner" is not assignable.
        $this->actingAs($admin)->patchJson($this->inviteUrl($workspace, '/members/' . $owner->id), ['role' => 'member'])
            ->assertUnprocessable();
        $this->actingAs($admin)->patchJson($this->inviteUrl($workspace, '/members/' . $member->id), ['role' => 'owner'])
            ->assertUnprocessable();
    }

    public function test_removing_members_and_revoking_invitations(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();
        $invitation = app(\LaraSaaS\Support\WorkspaceManager::class)->invite($workspace, 'temp@example.com', 'member', $owner);

        $this->actingAs($member)->deleteJson($this->inviteUrl($workspace, '/invitations/' . $invitation->id))->assertForbidden();

        $this->actingAs($admin)->deleteJson($this->inviteUrl($workspace, '/invitations/' . $invitation->id))->assertOk();
        $this->assertSame(0, $workspace->invitations()->where('email', 'temp@example.com')->count());

        $this->actingAs($admin)->deleteJson($this->inviteUrl($workspace, '/members/' . $owner->id))->assertUnprocessable();
        $this->actingAs($admin)->deleteJson($this->inviteUrl($workspace, '/members/' . $member->id))->assertOk();
        $this->assertFalse($workspace->hasMember($member));
    }

    public function test_updating_and_deleting_workspaces(): void
    {
        [$workspace, $owner, $admin, $member] = $this->team();

        $this->actingAs($member)->patchJson($this->inviteUrl($workspace), ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($admin)->patchJson($this->inviteUrl($workspace), ['name' => 'Acme LLC'])
            ->assertOk()->assertJsonPath('data.name', 'Acme LLC');

        // Only the owner may delete (workspace.delete) or transfer (ownership.transfer).
        $this->actingAs($admin)->deleteJson($this->inviteUrl($workspace))->assertForbidden();
        $this->actingAs($admin)->postJson($this->inviteUrl($workspace, '/transfer'), ['user_id' => $admin->id])->assertForbidden();

        $this->actingAs($owner)->postJson($this->inviteUrl($workspace, '/transfer'), ['user_id' => $admin->id])->assertOk();
        $this->assertSame($admin->id, $workspace->fresh()->owner_id);

        $this->actingAs($admin)->deleteJson($this->inviteUrl($workspace))->assertOk();
        $this->assertNull($workspace->fresh());
    }
}
