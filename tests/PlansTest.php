<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

class PlansTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('cors.paths', []); // Exercise package CORS, not the host's global middleware.
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', CatalogUser::class);
        $app['config']->set('auth.providers.users.model', CatalogUser::class);
        $app['config']->set('larasaas.admin_emails', ['admin@example.com']);
        $app['config']->set('larasaas.integration.api.cors_origins', ['https://client.example.com']);
    }

    protected function defineDatabaseMigrations(): void
    {
        (require __DIR__ . '/../database/schema/2026_10_14_000000_create_larasaas_schema.php')->up();
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'key' => 'starter', 'name' => 'Starter', 'description' => 'A plan',
            'active' => true, 'highlight' => false, 'badge' => null, 'trial_days' => 7, 'sort_order' => 1,
            'monthly' => ['price' => 9, 'stripe_id' => 'price_starter_monthly'],
            'yearly' => ['price' => 90, 'stripe_id' => 'price_starter_yearly'],
            'features' => ['uploads' => true, 'storage_gb' => 10],
        ], $overrides);
    }

    private function user(string $email = 'member@example.com'): CatalogUser
    {
        return CatalogUser::create(['email' => $email]);
    }

    public function test_database_is_the_only_plan_source_and_pricing_is_public(): void
    {
        config(['larasaas.pricing.plans' => ['legacy' => ['name' => 'Never shown']]]);
        $this->getJson('/api/larasaas/pricing')->assertOk()->assertJsonPath('data.plans', []);
        Plan::create($this->payload());
        $this->getJson('/api/larasaas/pricing')->assertOk()
            ->assertJsonPath('data.plans.starter.name', 'Starter')
            ->assertJsonPath('data.plans.starter.entitlements.storage_gb', 10)
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('subscription.subscribed', false);
        $this->get('/embed/pricing')->assertOk()->assertSee('Starter')->assertSee('Sign in to choose a plan')->assertDontSee('/embed/billing');
        $this->get('/app/pricing', ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('component', 'LaraSaaS/Pricing');
    }

    public function test_private_endpoints_still_require_authentication(): void
    {
        foreach (['subscription', 'invoices', 'announcements', 'features'] as $path) {
            $this->getJson('/api/larasaas/' . $path)->assertUnauthorized();
        }
        foreach (['subscribe/starter', 'swap/starter', 'cancel', 'resume', 'portal'] as $path) {
            $this->postJson('/api/larasaas/billing/' . $path)->assertUnauthorized();
        }
        $this->get('/embed/billing')->assertForbidden();
        $this->post('/embed/billing/cancel')->assertForbidden();
        $this->getJson('/app/billing')->assertUnauthorized();
        $this->getJson('/app/admin/plans')->assertUnauthorized();
        $this->actingAs($this->user())->postJson('/app/admin/plans', $this->payload())->assertForbidden();
    }

    public function test_admin_can_create_update_archive_and_restore_plans(): void
    {
        $this->actingAs($this->user('admin@example.com'));
        $this->get('/app/admin/plans', ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('component', 'LaraSaaS/Admin/Plans');
        $this->postJson('/app/admin/plans', $this->payload())->assertRedirect();
        $plan = Plan::firstOrFail();
        $this->assertSame(10, $plan->features['storage_gb']);
        $this->putJson('/app/admin/plans/' . $plan->id, $this->payload(['features' => ['uploads' => false, 'storage_gb' => 100]]))->assertRedirect();
        $this->assertSame(100, $plan->fresh()->features['storage_gb']);
        $this->deleteJson('/app/admin/plans/' . $plan->id)->assertRedirect();
        $this->assertSame([], app(LaraSaaS::class)->plans());
        $this->assertSame('starter', app(LaraSaaS::class)->planKeyForPrice('price_starter_monthly'));
        $this->putJson('/app/admin/plans/' . $plan->id, $this->payload())->assertRedirect();
        $this->assertNotEmpty(app(LaraSaaS::class)->plans());
    }

    public function test_validation_rejects_unknown_features_bad_types_and_price_collisions(): void
    {
        $this->actingAs($this->user('admin@example.com'));
        foreach ([['unknown' => true], ['storage_gb' => -2], ['storage_gb' => 1.5], ['uploads' => 'yes']] as $features) {
            $this->postJson('/app/admin/plans', $this->payload(['features' => $features]))->assertUnprocessable();
        }
        $this->postJson('/app/admin/plans', $this->payload(['features' => ['storage_gb' => -1]]))->assertRedirect();
        $plan = Plan::firstOrFail();
        $this->assertFalse($plan->features['uploads']);
        $this->postJson('/app/admin/plans', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('key');
        $this->postJson('/app/admin/plans', $this->payload(['key' => 'other']))->assertUnprocessable()->assertJsonValidationErrors('monthly.stripe_id');
        $this->putJson('/app/admin/plans/' . $plan->id, $this->payload(['monthly' => ['price' => 12, 'stripe_id' => 'price_new']]))->assertUnprocessable();
        $this->postJson('/app/admin/plans', $this->payload(['key' => 'empty', 'monthly' => null, 'yearly' => null]))->assertUnprocessable();
    }

    public function test_entitlements_follow_valid_subscription_and_survive_archiving(): void
    {
        $plan = Plan::create($this->payload());
        $user = $this->user();
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $manager = app(LaraSaaS::class);
        $this->assertFalse($manager->allows($request, 'uploads'));
        $this->assertFalse($manager->allows($request, 'storage_gb', 1));
        $user->billingState = new Subscription(['stripe_price' => 'price_starter_monthly', 'stripe_status' => 'active']);
        $this->assertTrue($manager->allows($request, 'uploads'));
        $this->assertTrue($manager->allows($request, 'storage_gb', 10));
        $this->assertFalse($manager->allows($request, 'storage_gb', 10.1));
        $this->assertNull($manager->entitlement($request, 'unknown'));
        $plan->update(['active' => false, 'features' => ['uploads' => true, 'storage_gb' => -1]]);
        $this->assertTrue($manager->allows($request, 'storage_gb', 999));
        $this->assertFalse($manager->allows($request, 'storage_gb', -1));
        $user->billingState->stripe_status = 'canceled';
        $user->billingState->ends_at = now()->subDay();
        $this->assertFalse($manager->allows($request, 'uploads'));
    }

    public function test_authenticated_pricing_includes_subscription(): void
    {
        Plan::create($this->payload());
        $user = $this->user();
        $user->billingState = new Subscription(['stripe_price' => 'price_starter_monthly', 'stripe_status' => 'active']);
        $this->actingAs($user)->getJson('/api/larasaas/pricing')->assertOk()
            ->assertJsonPath('authenticated', true)->assertJsonPath('subscription.plan', 'starter');
    }

    public function test_signed_embed_identity_is_optional_but_never_trusted_unsigned(): void
    {
        $user = $this->user();
        $manager = app(LaraSaaS::class);
        $this->get($manager->embedUrl())->assertOk();
        $signed = $manager->embedUrl($user);
        $this->get($signed)->assertOk()->assertSee('/embed/billing');
        $this->get('/embed/pricing?user=' . $user->id)->assertForbidden();
        $this->get($signed . '&extra=tampered')->assertForbidden();
        $expired = \Illuminate\Support\Facades\URL::temporarySignedRoute('larasaas.embed.pricing', now()->subMinute(), ['user' => $user->id]);
        $this->get($expired)->assertForbidden();
        $this->actingAs($user)->post('/embed/billing/cancel')->assertForbidden();
        $this->post($manager->embedUrl($user, 'billing.cancel'), ['user' => 123])->assertForbidden();
    }

    public function test_cors_preflight_does_not_require_login(): void
    {
        $this->options('/api/larasaas/billing/cancel', [], ['Origin' => 'https://client.example.com'])
            ->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'https://client.example.com');
    }

    public function test_public_pricing_can_resolve_an_optional_token_guard(): void
    {
        $user = $this->user();
        \Illuminate\Support\Facades\Auth::viaRequest('catalog-token', fn ($request) => $request->bearerToken() === 'valid-token' ? $user : null);
        config([
            'auth.guards.catalog' => ['driver' => 'catalog-token'],
            'larasaas.route.pricing_guard' => 'catalog',
        ]);
        $this->getJson('/api/larasaas/pricing', ['Authorization' => 'Bearer valid-token'])
            ->assertOk()->assertJsonPath('authenticated', true);
        \Illuminate\Support\Facades\Auth::forgetGuards();
        $this->getJson('/api/larasaas/pricing?user=' . $user->id, ['Authorization' => 'Bearer invalid-token'])
            ->assertOk()->assertJsonPath('authenticated', false);
    }

    public function test_workspace_entitlements_use_the_resolved_billable(): void
    {
        Plan::create($this->payload());
        $workspace = new CatalogUser;
        $workspace->billingState = new Subscription(['stripe_price' => 'price_starter_yearly', 'stripe_status' => 'trialing', 'trial_ends_at' => now()->addWeek()]);
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_resolver' => fn ($user) => $workspace,
        ]);
        $request = Request::create('/');
        $request->setUserResolver(fn () => new User);
        $this->assertSame(10, app(LaraSaaS::class)->entitlement($request, 'storage_gb'));
        $workspace->billingState->stripe_status = 'canceled';
        $workspace->billingState->trial_ends_at = null;
        $workspace->billingState->ends_at = now()->addDay();
        $this->assertTrue(app(LaraSaaS::class)->allows($request, 'uploads'));
    }

    public function test_catalog_can_be_added_to_without_a_schema_change(): void
    {
        config(['larasaas.plan_features.bandwidth' => ['type' => 'number', 'default' => 0, 'unit' => 'GB']]);
        $this->actingAs($this->user('admin@example.com'))
            ->postJson('/app/admin/plans', $this->payload(['features' => ['bandwidth' => 1.5]]))->assertRedirect();
        $this->assertSame(1.5, Plan::first()->features['bandwidth']);
    }
}

class CatalogUser extends User
{
    use Billable;

    protected $table = 'users';
    protected $guarded = [];
    public ?Subscription $billingState = null;

    public function subscription($type = 'default')
    {
        return $this->billingState;
    }

    public function subscribed($type = 'default', $price = null)
    {
        return $this->billingState?->valid() ?? false;
    }
}
