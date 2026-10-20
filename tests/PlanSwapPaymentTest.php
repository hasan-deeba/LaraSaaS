<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

class PlanSwapPaymentTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('c', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', SwapTestUser::class);
        $app['config']->set('auth.providers.users.model', SwapTestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->timestamps();
        });

        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
    }

    private function seedCatalog(): void
    {
        Plan::create([
            'key' => 'starter',
            'name' => 'Starter',
            'description' => 'Entry plan',
            'active' => true,
            'trial_days' => 14,
            'sort_order' => 1,
            'monthly' => ['price' => 9, 'stripe_id' => 'price_starter_monthly'],
            'yearly' => ['price' => 90, 'stripe_id' => 'price_starter_yearly'],
            'features' => ['uploads' => false, 'storage_gb' => 5],
        ]);

        Plan::create([
            'key' => 'pro',
            'name' => 'Pro',
            'description' => 'Higher tier plan',
            'active' => true,
            'trial_days' => 14,
            'sort_order' => 2,
            'monthly' => ['price' => 29, 'stripe_id' => 'price_pro_monthly'],
            'yearly' => ['price' => 290, 'stripe_id' => 'price_pro_yearly'],
            'features' => ['uploads' => true, 'storage_gb' => 100],
        ]);

        Plan::create([
            'key' => 'notrial',
            'name' => 'No Trial Basic',
            'description' => 'Cheaper plan with explicit 0-day trial',
            'active' => true,
            'trial_days' => 0,
            'sort_order' => 0,
            'monthly' => ['price' => 5, 'stripe_id' => 'price_notrial_monthly'],
            'yearly' => null,
            'features' => ['uploads' => false, 'storage_gb' => 2],
        ]);
    }

    protected function tearDown(): void
    {
        SwapTestUser::$subscriptionsById = [];
        parent::tearDown();
    }

    public function test_paid_upgrade_immediately_invoices_and_enforces_error_if_payment_fails(): void
    {
        $this->seedCatalog();
        $user = SwapTestUser::create(['email' => 'paid@example.com']);
        $sub = new RecordingSubscription([
            'stripe_price' => 'price_starter_monthly',
            'stripe_status' => 'active',
        ]);
        SwapTestUser::$subscriptionsById[$user->id] = $sub;

        $manager = app(LaraSaaS::class);
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'pro']), ['interval' => 'monthly'])
            ->assertRedirect();

        $this->assertTrue($sub->errorIfPaymentFailsCalled);
        $this->assertFalse($sub->skipTrialCalled);
        $this->assertSame('price_pro_monthly', $sub->swappedAndInvoicedTo);
    }

    public function test_trial_upgrades_end_trial_immediately_while_pure_trial_downgrade_preserves_trial(): void
    {
        $this->seedCatalog();
        $user = SwapTestUser::create(['email' => 'trial@example.com']);
        $manager = app(LaraSaaS::class);

        // 1. Cheap monthly trial ($9/mo) -> Expensive monthly ($29/mo): ends trial & charges immediately.
        $sub1 = new RecordingSubscription([
            'stripe_price' => 'price_starter_monthly',
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);
        SwapTestUser::$subscriptionsById[$user->id] = $sub1;
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'pro']), ['interval' => 'monthly'])
            ->assertRedirect();
        $this->assertTrue($sub1->skipTrialCalled);
        $this->assertTrue($sub1->errorIfPaymentFailsCalled);
        $this->assertSame('price_pro_monthly', $sub1->swappedAndInvoicedTo);

        // 2. Cheap yearly trial ($90/yr = $7.50/mo) -> Expensive monthly ($29/mo): higher monthly rate still ends trial!
        $sub2 = new RecordingSubscription([
            'stripe_price' => 'price_starter_yearly',
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);
        SwapTestUser::$subscriptionsById[$user->id] = $sub2;
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'pro']), ['interval' => 'monthly'])
            ->assertRedirect();
        $this->assertTrue($sub2->skipTrialCalled);
        $this->assertSame('price_pro_monthly', $sub2->swappedAndInvoicedTo);

        // 3. Expensive monthly trial ($29/mo) -> Cheaper plan with `trial_days = 0`: ends trial & charges immediately!
        $sub3 = new RecordingSubscription([
            'stripe_price' => 'price_pro_monthly',
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);
        SwapTestUser::$subscriptionsById[$user->id] = $sub3;
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'notrial']), ['interval' => 'monthly'])
            ->assertRedirect();
        $this->assertTrue($sub3->skipTrialCalled);
        $this->assertSame('price_notrial_monthly', $sub3->swappedAndInvoicedTo);

        // 4. Expensive monthly trial ($29/mo) -> Cheaper trial-eligible plan ($9/mo): keeps remaining trial days.
        $sub4 = new RecordingSubscription([
            'stripe_price' => 'price_pro_monthly',
            'stripe_status' => 'trialing',
            'trial_ends_at' => now()->addDays(10),
        ]);
        SwapTestUser::$subscriptionsById[$user->id] = $sub4;
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'starter']), ['interval' => 'monthly'])
            ->assertRedirect();
        $this->assertFalse($sub4->skipTrialCalled);
        $this->assertTrue($sub4->errorIfPaymentFailsCalled);
        $this->assertSame('price_starter_monthly', $sub4->swappedAndInvoicedTo);
    }

    public function test_failed_card_blocks_upgrade_and_surfaces_clean_error_across_embed_inertia_and_api(): void
    {
        $this->seedCatalog();
        $user = SwapTestUser::create(['email' => 'declined@example.com']);
        $sub = new RecordingSubscription([
            'stripe_price' => 'price_starter_monthly',
            'stripe_status' => 'active',
        ]);
        $sub->throwOnSwap = \Stripe\Exception\CardException::factory('Your card has insufficient funds.');
        SwapTestUser::$subscriptionsById[$user->id] = $sub;

        $manager = app(LaraSaaS::class);

        // 1. Embed surface redirects back to `/embed/billing?notice=payment_failed` with warning banner; plan stays Starter.
        $embedRes = $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'pro']), ['interval' => 'monthly']);
        $embedRes->assertRedirect();
        $redirectLocation = (string) $embedRes->headers->get('Location');
        $this->assertStringContainsString('notice=payment_failed', $redirectLocation);
        $this->get($redirectLocation)
            ->assertOk()
            ->assertSee('ls-notice is-warn', false)
            ->assertSee('Payment could not be completed')
            ->assertSee('Starter');
        $this->assertSame('price_starter_monthly', $sub->stripe_price);

        // 2. JSON API surface returns HTTP 402 Payment Required; plan stays Starter.
        $this->actingAs($user)
            ->postJson('/api/larasaas/billing/swap/pro', ['interval' => 'monthly'])
            ->assertStatus(402)
            ->assertJsonPath('message', __('larasaas::larasaas.billing.payment_failed'));
        $this->assertSame('price_starter_monthly', $sub->stripe_price);

        // 3. Inertia web surface flashes notice and redirects cleanly without 500; plan stays Starter.
        $this->actingAs($user)
            ->from('/app/pricing')
            ->post('/app/billing/swap/pro', ['interval' => 'monthly'])
            ->assertRedirect('/app/pricing')
            ->assertSessionHas('larasaas.notice', __('larasaas::larasaas.billing.payment_failed'));
        $this->assertSame('price_starter_monthly', $sub->stripe_price);
    }
}

class SwapTestUser extends User
{
    use Billable;

    /** @var array<int|string, Subscription> */
    public static array $subscriptionsById = [];

    protected $table = 'users';
    protected $guarded = [];

    public function subscription($type = 'default')
    {
        return self::$subscriptionsById[$this->id] ?? null;
    }

    public function subscribed($type = 'default', $price = null)
    {
        return $this->subscription($type)?->valid() ?? false;
    }
}

class RecordingSubscription extends Subscription
{
    public bool $skipTrialCalled = false;
    public bool $errorIfPaymentFailsCalled = false;
    public ?string $swappedAndInvoicedTo = null;
    public ?\Throwable $throwOnSwap = null;

    public function skipTrial()
    {
        $this->skipTrialCalled = true;

        return parent::skipTrial();
    }

    public function errorIfPaymentFails()
    {
        $this->errorIfPaymentFailsCalled = true;

        return parent::errorIfPaymentFails();
    }

    public function swapAndInvoice($prices, array $options = [])
    {
        if ($this->throwOnSwap !== null) {
            throw $this->throwOnSwap;
        }

        $this->swappedAndInvoicedTo = is_array($prices) ? reset($prices) : $prices;
        $this->stripe_price = $this->swappedAndInvoicedTo;

        return $this;
    }
}
