<?php

namespace LaraSaaS\Tests;

use Illuminate\Foundation\Auth\User;

/**
 * The multi-tenant choice in config: when `larasaas.multi_tenant.enabled`
 * is false (the default), no workspace/team surface exists at all.
 */
class SingleTenantTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', SoloUser::class);
        $app['config']->set('auth.providers.users.model', SoloUser::class);
        // multi_tenant.enabled intentionally left at its default: false.
    }

    public function test_workspace_and_team_routes_are_absent_for_single_tenant_installs(): void
    {
        $this->actingAs(new SoloUser(['id' => 1, 'email' => 'solo@example.com']));

        // 404 = no such URI; 405 = only the CORS OPTIONS catch-all matches —
        // either way the workspace/team surface does not exist.
        foreach ([
            ['GET', '/api/larasaas/workspaces'],
            ['POST', '/api/larasaas/workspaces'],
            ['GET', '/api/larasaas/workspaces/1'],
            ['GET', '/api/larasaas/workspaces/1/invitations'],
            ['POST', '/api/larasaas/invitations/token/accept'],
            ['GET', '/app/workspaces'],
        ] as [$method, $uri]) {
            $response = $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
            $this->assertContains($response->getStatusCode(), [404, 405], "{$method} {$uri} should not be routed, got {$response->getStatusCode()}");
        }
    }
}

class SoloUser extends User
{
    protected $table = 'users';
    protected $guarded = [];
}
