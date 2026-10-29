<?php

namespace LaraSaaS\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LaraSaaS\Facades\LaraSaaS;
use LaraSaaS\Models\Plan;

class PlanManagementTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app); // custom mode: no package admin or embed routes
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        // The schema migration resolves the billable table from config; this
        // test exercises plan management without any real users.
        $app['config']->set('larasaas.user_model', ManagementUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->id();
        });
        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
    }

    private function payload(array $attributes = []): array
    {
        return array_replace([
            'key' => 'starter', 'name' => 'Starter',
            'monthly' => ['price' => '9.50', 'stripe_id' => 'price_starter'],
            'features' => ['uploads' => true, 'storage_gb' => '10'],
        ], $attributes);
    }

    public function test_host_dashboard_can_manage_plans_without_package_ui_or_user(): void
    {
        $this->assertFalse(Route::has('larasaas.admin.plans.index'));
        $this->assertFalse(Route::has('larasaas.embed.pricing'));
        $plan = LaraSaaS::createPlan($this->payload(['id' => 999, 'untrusted' => 'ignored']));
        $this->assertInstanceOf(Plan::class, $plan);
        $this->assertNotEquals(999, $plan->id);
        $this->assertTrue($plan->active);
        $this->assertFalse($plan->highlight);
        $this->assertSame(0, $plan->sort_order);
        $this->assertSame(10, $plan->features['storage_gb']);
        $this->assertEquals(9.5, $plan->monthly['price']);
        $this->assertNull($plan->yearly);
        $this->assertArrayHasKey('starter', LaraSaaS::plans());

        LaraSaaS::updatePlan($plan, ['name' => 'Renamed', 'features' => ['storage_gb' => 20]]);
        $this->assertSame('Renamed', $plan->fresh()->name);
        $this->assertSame(20, $plan->features['storage_gb']);
        $this->assertFalse($plan->features['uploads']); // replacement map uses defaults
        $this->assertSame('price_starter', $plan->monthly['stripe_id']);

        LaraSaaS::archivePlan($plan);
        $this->assertSame([], LaraSaaS::plans());
        $this->assertSame('starter', LaraSaaS::planKeyForPrice('price_starter'));
        $this->assertSame(1, Plan::count());
        LaraSaaS::updatePlan($plan, ['active' => true]);
        $this->assertArrayHasKey('starter', LaraSaaS::plans());
    }

    public function test_create_persists_configured_trial_days_when_omitted_or_blank(): void
    {
        config(['larasaas.billing.trial_days' => 21]);

        $omitted = LaraSaaS::createPlan($this->payload());
        $this->assertSame(21, $omitted->trial_days);

        $blank = LaraSaaS::createPlan($this->payload([
            'key' => 'blank-trial',
            'monthly' => ['price' => 5, 'stripe_id' => 'price_blank_trial'],
            'trial_days' => '',
        ]));
        $this->assertSame(21, $blank->trial_days);

        $noTrial = LaraSaaS::createPlan($this->payload([
            'key' => 'no-trial',
            'monthly' => ['price' => 5, 'stripe_id' => 'price_no_trial'],
            'trial_days' => 0,
        ]));
        $this->assertSame(0, $noTrial->trial_days);

        LaraSaaS::updatePlan($blank, ['trial_days' => '']);
        $this->assertSame(21, $blank->fresh()->trial_days);
    }

    public function test_create_defaults_feature_values_when_omitted(): void
    {
        $data = $this->payload();
        unset($data['features']);
        $plan = LaraSaaS::createPlan($data);
        $this->assertSame(['uploads' => false, 'storage_gb' => 0], $plan->features);
    }

    public function test_empty_feature_map_resets_values_to_catalog_defaults(): void
    {
        $plan = LaraSaaS::createPlan($this->payload());
        LaraSaaS::updatePlan($plan, ['features' => []]);
        $this->assertSame(['uploads' => false, 'storage_gb' => 0], $plan->fresh()->features);
    }

    public function test_plans_work_with_an_empty_feature_catalog(): void
    {
        config(['larasaas.plan_features' => []]);
        $plan = LaraSaaS::createPlan($this->payload(['features' => []]));
        $this->assertSame([], $plan->features);
        LaraSaaS::updatePlan($plan, ['name' => 'No capabilities']);
        $this->assertSame([], $plan->fresh()->features);
    }

    public function test_create_uses_validation_without_an_http_request(): void
    {
        foreach ([
            ['features' => ['unknown' => true]],
            ['features' => ['uploads' => 'yes']],
            ['features' => ['storage_gb' => -2]],
            ['features' => null],
            ['monthly' => null],
            ['monthly' => ['price' => -1, 'stripe_id' => 'invalid']],
            ['yearly' => ['price' => 95, 'stripe_id' => 'price_starter']],
            ['key' => 'Invalid Key'],
        ] as $invalid) {
            try {
                LaraSaaS::createPlan($this->payload($invalid));
                $this->fail('Invalid catalog data was accepted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertSame(0, Plan::count());
            }
        }
    }

    public function test_update_cannot_change_subscription_identifiers(): void
    {
        $plan = LaraSaaS::createPlan($this->payload());
        foreach ([['key' => 'changed'], ['monthly' => null], ['monthly' => ['price' => 10, 'stripe_id' => 'price_new']]] as $invalid) {
            try {
                LaraSaaS::updatePlan($plan, $invalid);
                $this->fail('Subscription identifier was changed.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertSame('starter', $plan->fresh()->key);
                $this->assertSame('price_starter', $plan->fresh()->monthly['stripe_id']);
            }
        }
    }

    public function test_archived_plan_keys_and_prices_cannot_be_reused(): void
    {
        LaraSaaS::archivePlan(LaraSaaS::createPlan($this->payload()));
        foreach ([[], ['key' => 'another', 'monthly' => null, 'yearly' => ['price' => 95, 'stripe_id' => 'price_starter']]] as $duplicate) {
            try {
                LaraSaaS::createPlan($this->payload($duplicate));
                $this->fail('Archived subscription identifiers were reused.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
                $this->assertSame(1, Plan::count());
            }
        }
    }
}

class ManagementUser extends \Illuminate\Foundation\Auth\User
{
    protected $table = 'users';
    protected $guarded = [];
}
