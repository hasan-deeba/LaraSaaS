<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use LaraSaaS\Models\Workspace;
use LaraSaaS\Models\WorkspaceInvitation;
use LaraSaaS\Models\WorkspaceMember;
use LaraSaaS\Support\WorkspaceManager;
use LaraSaaS\Support\WorkspaceRole;

/**
 * Multi-tenant workspaces & team management: creation, granular roles
 * (Owner, Admin, Member), the invitation lifecycle and ownership rules.
 */
class WorkspacesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
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

    private function user(string $email = 'owner@example.com'): \App\Models\User
    {
        return \App\Models\User::create(['email' => $email]);
    }

    private function manager(): WorkspaceManager
    {
        return app(WorkspaceManager::class);
    }

    /*
    |----------------------------------------------------------------------
    | Workspaces
    |----------------------------------------------------------------------
    */

    public function test_creating_a_workspace_enrolls_the_owner(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme Inc'], $owner);

        $this->assertSame('acme-inc', $workspace->slug);
        $this->assertSame($owner->id, $workspace->owner_id);
        $this->assertTrue($workspace->isOwnedBy($owner));
        $this->assertSame(WorkspaceRole::Owner, $workspace->roleFor($owner));

        $membership = $workspace->membershipFor($owner);
        $this->assertTrue($membership->isOwner());
    }

    public function test_slugs_are_unique_and_follow_the_name(): void
    {
        $owner = $this->user();
        $first = $this->manager()->create(['name' => 'Acme Inc'], $owner);
        $second = $this->manager()->create(['name' => 'Acme Inc'], $owner);

        $this->assertSame('acme-inc', $first->slug);
        $this->assertSame('acme-inc-2', $second->slug);

        $custom = $this->manager()->create(['name' => 'Custom', 'slug' => 'my-team'], $owner);
        $this->assertSame('my-team', $custom->slug);
    }

    public function test_workspace_names_and_slugs_are_validated(): void
    {
        $owner = $this->user();

        foreach ([[], ['name' => str_repeat('x', 200)], ['name' => 'ok', 'slug' => 'Bad Slug!']] as $invalid) {
            try {
                $this->manager()->create($invalid, $owner);
                $this->fail('Expected validation to fail for ' . json_encode($invalid));
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }
    }

    /*
    |----------------------------------------------------------------------
    | Granular roles
    |----------------------------------------------------------------------
    */

    public function test_roles_have_granular_permissions(): void
    {
        $owner = WorkspaceRole::Owner;
        $admin = WorkspaceRole::Admin;
        $member = WorkspaceRole::Member;

        $this->assertTrue($owner->allows('workspace.delete'));
        $this->assertTrue($owner->allows('ownership.transfer'));
        $this->assertFalse($admin->allows('workspace.delete'));
        $this->assertFalse($admin->allows('ownership.transfer'));
        $this->assertTrue($admin->allows('members.invite'));
        $this->assertTrue($admin->allows('invitations.manage'));
        $this->assertFalse($member->allows('members.invite'));
        $this->assertTrue($member->allows('members.view'));

        $this->assertTrue($owner->isAtLeast($admin));
        $this->assertFalse($admin->isAtLeast($owner));

        config(['larasaas.workspaces.role_permissions' => ['member' => ['members.invite']]]);
        $this->assertTrue(WorkspaceRole::Member->allows('members.invite'));
        $this->assertFalse(WorkspaceRole::Member->allows('members.view'));
    }

    public function test_only_admin_and_member_roles_are_assignable(): void
    {
        $this->assertSame(['admin', 'member'], array_map(fn ($r) => $r->value, WorkspaceRole::assignable()));

        $workspace = $this->manager()->create(['name' => 'Acme'], $this->user());
        $member = $this->user('member@example.com');
        $this->manager()->invite($workspace, 'member@example.com', 'admin');
        $this->manager()->acceptByToken($workspace->invitations()->first()->token, $member);

        foreach (['owner', 'superuser', ''] as $role) {
            try {
                $this->manager()->updateMemberRole($workspace, $member, $role);
                $this->fail("Role [{$role}] must not be assignable");
            } catch (ValidationException) {
                $this->assertTrue(true);
            }
        }

        $this->manager()->updateMemberRole($workspace, $member, 'member');
        $this->assertSame(WorkspaceRole::Member, $workspace->roleFor($member));
    }

    /*
    |----------------------------------------------------------------------
    | Invitations
    |----------------------------------------------------------------------
    */

    public function test_invitations_carry_role_token_and_expiry(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);

        $invitation = $this->manager()->invite($workspace, 'Teammate@Example.com', 'admin', $owner);

        $this->assertSame('teammate@example.com', $invitation->email);
        $this->assertSame(WorkspaceRole::Admin, $invitation->roleEnum());
        $this->assertSame(64, strlen($invitation->token));
        $this->assertTrue($invitation->isPending());
        $this->assertFalse($invitation->isExpired());
        $this->assertNotNull($invitation->expires_at);
        $this->assertStringContainsString($invitation->token, $invitation->acceptUrl());
    }

    public function test_re_inviting_a_pending_email_resends_with_a_fresh_token(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);

        $first = $this->manager()->invite($workspace, 'teammate@example.com');
        $second = $this->manager()->invite($workspace, 'teammate@example.com', 'admin');

        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->token, $second->token);
        $this->assertSame(WorkspaceRole::Admin, $second->roleEnum());
        $this->assertSame(1, $workspace->invitations()->count());
    }

    public function test_accepting_an_invitation_creates_the_membership(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);
        $invitation = $this->manager()->invite($workspace, 'teammate@example.com', 'admin');
        $teammate = $this->user('teammate@example.com');

        $membership = $this->manager()->acceptByToken($invitation->token, $teammate);

        $this->assertSame(WorkspaceRole::Admin, $workspace->roleFor($teammate));
        $this->assertTrue($invitation->fresh()->isAccepted());
        $this->assertSame(WorkspaceRole::Admin, $membership->roleEnum());

        try {
            $this->manager()->acceptByToken($invitation->token, $teammate);
            $this->fail('A second accept must fail');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already been accepted', $e->getMessage());
        }
    }

    public function test_invitations_must_match_the_invited_email_and_not_be_expired(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);
        $invitation = $this->manager()->invite($workspace, 'teammate@example.com');

        try {
            $this->manager()->acceptByToken($invitation->token, $this->user('intruder@example.com'));
            $this->fail('A different e-mail must not be able to accept');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('different e-mail', $e->getMessage());
        }

        $invitation->expires_at = now()->subDay();
        $invitation->save();

        try {
            $this->manager()->acceptByToken($invitation->token, $this->user('teammate@example.com'));
            $this->fail('An expired invitation must not be acceptable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('expired', $e->getMessage());
        }

        try {
            $this->manager()->acceptByToken('nope', $this->user('teammate@example.com'));
            $this->fail('An unknown token must fail');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('invalid', $e->getMessage());
        }
    }

    public function test_cancelling_and_resending_invitations(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);
        $invitation = $this->manager()->invite($workspace, 'teammate@example.com');
        $previousToken = $invitation->token;

        $resent = $this->manager()->resendInvitation($invitation);
        $this->assertNotSame($previousToken, $resent->token);
        $this->assertTrue($resent->isPending());

        $this->manager()->cancelInvitation($resent);
        $this->assertSame(0, WorkspaceInvitation::query()->count());
    }

    public function test_already_members_cannot_be_invited(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);

        try {
            $this->manager()->invite($workspace, 'owner@example.com');
            $this->fail('Owners are already members');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('already a member', $e->getMessage());
        }
    }

    /*
    |----------------------------------------------------------------------
    | Membership rules
    |----------------------------------------------------------------------
    */

    public function test_the_owner_can_never_be_demoted_or_removed(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);

        try {
            $this->manager()->updateMemberRole($workspace, $owner, 'admin');
            $this->fail('The owner must not be demotable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Transfer ownership', $e->getMessage());
        }

        try {
            $this->manager()->removeMember($workspace, $owner);
            $this->fail('The owner must not be removable');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Transfer ownership', $e->getMessage());
        }
    }

    public function test_removing_members_and_the_non_member_guard(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);
        $teammate = $this->user('teammate@example.com');
        $this->manager()->invite($workspace, 'teammate@example.com');
        $this->manager()->acceptByToken($workspace->invitations()->first()->token, $teammate);

        $outsider = $this->user('outsider@example.com');
        try {
            $this->manager()->removeMember($workspace, $outsider);
            $this->fail('Non-members cannot be removed');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        $this->manager()->removeMember($workspace, $teammate);
        $this->assertFalse($workspace->hasMember($teammate));
    }

    public function test_transferring_ownership_keeps_the_roles_consistent(): void
    {
        $owner = $this->user();
        $workspace = $this->manager()->create(['name' => 'Acme'], $owner);
        $successor = $this->user('successor@example.com');
        $this->manager()->invite($workspace, 'successor@example.com');
        $this->manager()->acceptByToken($workspace->invitations()->first()->token, $successor);

        $this->manager()->transferOwnership($workspace, $successor);

        $this->assertSame($successor->id, $workspace->fresh()->owner_id);
        $this->assertSame(WorkspaceRole::Owner, $workspace->roleFor($successor));
        $this->assertSame(WorkspaceRole::Admin, $workspace->roleFor($owner));
    }

    /*
    |----------------------------------------------------------------------
    | HasWorkspaces trait (host user model)
    |----------------------------------------------------------------------
    */

    public function test_host_user_model_helpers(): void
    {
        $owner = $this->user();
        $member = $this->user('member@example.com');

        $one = $this->manager()->create(['name' => 'One'], $owner);
        $two = $this->manager()->create(['name' => 'Two'], $owner);
        $this->manager()->invite($two, 'member@example.com');
        $this->manager()->acceptByToken($two->invitations()->first()->token, $member);

        // The fixture user model opts into the shipped trait.
        $this->assertTrue(method_exists($owner, 'workspaces'));
        $this->assertCount(2, $owner->ownedWorkspaces()->get());
        $this->assertSame($one->id, $owner->currentWorkspace()?->id); // fallback = first membership
        $this->assertSame($two->id, $owner->switchWorkspace($two)?->id);
        $this->assertSame($two->id, $owner->fresh()->currentWorkspace()?->id);

        $this->assertTrue($member->isMemberOf($two));
        $this->assertFalse($member->isMemberOf($one));
        $this->assertSame(WorkspaceRole::Member, $member->roleIn($two));
        $this->assertTrue($member->canInWorkspace($two, 'members.view'));
        $this->assertFalse($member->canInWorkspace($two, 'members.invite'));

        $this->assertCount(1, $this->manager()->workspaceModel()::query()->visibleTo($member)->get());
    }
}
