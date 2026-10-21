<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Subscription;

class MultiSubscriptionSyncTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('m', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cashier.secret', 'sk_test_fake_sync_secret');
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', SyncTestUser::class);
        $app['config']->set('auth.providers.users.model', SyncTestUser::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cashier::useSubscriptionModel(TrackingCashierSubscription::class);
        $this->app->bind(\Stripe\StripeClient::class, fn () => new FakeStripeClient);
        FakeStripeSubscriptionsEndpoint::$byCustomer = [];
        SyncTestUser::$checkoutOpened = false;
        TrackingCashierSubscription::resetLog();

        Route::middleware(['web', 'larasaas.subscribed'])->get('/_test/gated', fn () => response('ok'));
    }

    protected function tearDown(): void
    {
        Cashier::useSubscriptionModel(Subscription::class);
        FakeStripeSubscriptionsEndpoint::$byCustomer = [];
        TrackingCashierSubscription::resetLog();
        parent::tearDown();
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->string('type');
            $table->string('stripe_id')->unique();
            $table->string('stripe_status');
            $table->string('stripe_price')->nullable();
            $table->integer('quantity')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'stripe_status']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id');
            $table->string('stripe_id')->unique();
            $table->string('stripe_product');
            $table->string('stripe_price');
            $table->integer('quantity')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'stripe_price']);
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
            'features' => ['uploads' => false],
        ]);

        Plan::create([
            'key' => 'pro',
            'name' => 'Pro',
            'description' => 'Growth plan',
            'active' => true,
            'trial_days' => 14,
            'sort_order' => 2,
            'monthly' => ['price' => 29, 'stripe_id' => 'price_pro_monthly'],
            'yearly' => ['price' => 290, 'stripe_id' => 'price_pro_yearly'],
            'features' => ['uploads' => true],
        ]);

        Plan::create([
            'key' => 'business',
            'name' => 'Business',
            'description' => 'Scale plan',
            'active' => true,
            'trial_days' => 14,
            'sort_order' => 3,
            'monthly' => ['price' => 79, 'stripe_id' => 'price_business_monthly'],
            'yearly' => ['price' => 790, 'stripe_id' => 'price_business_yearly'],
            'features' => ['uploads' => true],
        ]);
    }

    private function stripeSubObject(array $overrides = []): object
    {
        $id = $overrides['id'] ?? 'sub_' . uniqid();
        $priceId = $overrides['price'] ?? 'price_starter_monthly';
        $trialEnd = array_key_exists('trial_end', $overrides) ? $overrides['trial_end'] : now()->addDays(14)->timestamp;
        $periodEnd = $overrides['current_period_end'] ?? ($trialEnd ?: now()->addMonth()->timestamp);

        return (object) [
            'id' => $id,
            'status' => $overrides['status'] ?? 'trialing',
            'created' => $overrides['created'] ?? now()->timestamp,
            'trial_end' => $trialEnd,
            'current_period_end' => $periodEnd,
            'cancel_at_period_end' => $overrides['cancel_at_period_end'] ?? false,
            'cancel_at' => $overrides['cancel_at'] ?? null,
            'canceled_at' => $overrides['canceled_at'] ?? null,
            'ended_at' => $overrides['ended_at'] ?? null,
            'metadata' => (object) ($overrides['metadata'] ?? ['type' => 'default']),
            'items' => (object) [
                'data' => [
                    (object) [
                        'id' => 'si_' . $id,
                        'quantity' => 1,
                        'current_period_end' => $periodEnd,
                        'price' => (object) [
                            'id' => $priceId,
                            'product' => 'prod_larasaas',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function test_syncs_stripe_subscription_without_webhook_and_swaps_instead_of_opening_duplicate_checkout(): void
    {
        $this->seedCatalog();
        $user = SyncTestUser::create([
            'email' => 'nowebhook@example.com',
            'stripe_id' => 'cus_nowebhook_1',
        ]);

        // Local DB has ZERO subscription rows (`stripe listen` not running),
        // while Stripe already has the active trial from Checkout #1.
        $this->assertSame(0, $user->subscriptions()->count());

        FakeStripeSubscriptionsEndpoint::$byCustomer['cus_nowebhook_1'] = [
            $this->stripeSubObject([
                'id' => 'sub_checkout_1',
                'price' => 'price_starter_monthly',
                'status' => 'trialing',
                'trial_end' => now()->addDays(14)->timestamp,
            ]),
        ];

        $manager = app(LaraSaaS::class);

        // User immediately clicks "Pro" without any webhook having run:
        // must sync `sub_checkout_1`, skip the trial, swap & invoice immediately,
        // and NEVER open a second Stripe Checkout!
        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'pro']), ['interval' => 'monthly'])
            ->assertRedirect();

        $this->assertFalse(SyncTestUser::$checkoutOpened);
        $this->assertSame(1, $user->fresh()->subscriptions()->count());
        $this->assertSame(['sub_checkout_1' => 'price_pro_monthly'], TrackingCashierSubscription::$swapped);
        $this->assertContains('sub_checkout_1', TrackingCashierSubscription::$skippedTrial);
        $this->assertContains('sub_checkout_1', TrackingCashierSubscription::$enforcedPayment);
    }

    public function test_cancel_terminates_all_duplicate_parallel_subscriptions_and_updates_embed_billing_and_pricing_ui(): void
    {
        $this->seedCatalog();
        $trialEnd = now()->addDays(12)->startOfSecond();

        $user = SyncTestUser::create([
            'email' => 'multi@example.com',
            'stripe_id' => 'cus_multi_3',
        ]);

        // Exact real-world failure mode: 3 subscriptions exist in Stripe:
        // - sub_1 (Starter, active trial)
        // - sub_2 (Pro, active trial)
        // - sub_3 (Business, newest row, already on grace period from earlier partial cancel)
        FakeStripeSubscriptionsEndpoint::$byCustomer['cus_multi_3'] = [
            $this->stripeSubObject([
                'id' => 'sub_3',
                'price' => 'price_business_monthly',
                'status' => 'trialing',
                'created' => now()->subMinute()->timestamp,
                'trial_end' => $trialEnd->timestamp,
                'cancel_at_period_end' => true,
            ]),
            $this->stripeSubObject([
                'id' => 'sub_2',
                'price' => 'price_pro_monthly',
                'status' => 'trialing',
                'created' => now()->subMinutes(2)->timestamp,
                'trial_end' => $trialEnd->timestamp,
            ]),
            $this->stripeSubObject([
                'id' => 'sub_1',
                'price' => 'price_starter_monthly',
                'status' => 'trialing',
                'created' => now()->subMinutes(3)->timestamp,
                'trial_end' => $trialEnd->timestamp,
            ]),
        ];

        $manager = app(LaraSaaS::class);

        // Visiting `/embed/billing` sees that uncanceled subscriptions STILL exist
        // (`sub_2` / `sub_1`) rather than treating `sub_3` as already canceled!
        $this->get($manager->embedUrl($user, 'billing'))
            ->assertOk()
            ->assertSee('Cancel subscription');

        // Clicking Cancel ONCE cancels the primary subscription cleanly AND
        // immediately terminates ALL extra parallel subscriptions in Stripe!
        $this->post($manager->embedUrl($user, 'billing.cancel'))
            ->assertRedirect();

        $this->assertCount(1, TrackingCashierSubscription::$canceledGracefully);
        $this->assertCount(2, TrackingCashierSubscription::$canceledImmediately);

        // Reflect the updated Stripe state on the fake endpoint for the redirect GET:
        $keptId = TrackingCashierSubscription::$canceledGracefully[0];
        FakeStripeSubscriptionsEndpoint::$byCustomer['cus_multi_3'] = [
            $this->stripeSubObject([
                'id' => $keptId,
                'price' => 'price_pro_monthly',
                'status' => 'trialing',
                'trial_end' => $trialEnd->timestamp,
                'cancel_at_period_end' => true,
            ]),
            $this->stripeSubObject([
                'id' => 'sub_ended_a',
                'price' => 'price_business_monthly',
                'status' => 'canceled',
                'trial_end' => null,
                'ended_at' => now()->timestamp,
            ]),
            $this->stripeSubObject([
                'id' => 'sub_ended_b',
                'price' => 'price_starter_monthly',
                'status' => 'canceled',
                'trial_end' => null,
                'ended_at' => now()->timestamp,
            ]),
        ];

        // `/embed/billing` MUST render warning `Cancels on YYYY-MM-DD` + `Resume subscription`
        // (and NEVER render the green `Free trial` badge over a canceled trial!).
        $this->get($manager->embedUrl($user, 'billing'))
            ->assertOk()
            ->assertSee('ls-badge is-warn', false)
            ->assertSee('Cancels on ' . $trialEnd->toDateString())
            ->assertSee('Resume subscription')
            ->assertDontSee('<span class="ls-badge is-ok">Free trial</span>', false);

        // `/embed/pricing` MUST also show warning `Cancels on YYYY-MM-DD` and NO free-trial badge.
        $this->get($manager->embedUrl($user, 'pricing'))
            ->assertOk()
            ->assertSee('Cancels on ' . $trialEnd->toDateString())
            ->assertDontSee('14-day free trial');
    }

    public function test_swap_collapses_duplicate_parallel_subscriptions_down_to_one(): void
    {
        $this->seedCatalog();
        $user = SyncTestUser::create([
            'email' => 'collapse@example.com',
            'stripe_id' => 'cus_collapse_1',
        ]);

        FakeStripeSubscriptionsEndpoint::$byCustomer['cus_collapse_1'] = [
            $this->stripeSubObject(['id' => 'sub_a', 'price' => 'price_starter_monthly', 'status' => 'trialing']),
            $this->stripeSubObject(['id' => 'sub_b', 'price' => 'price_pro_monthly', 'status' => 'trialing']),
        ];

        $manager = app(LaraSaaS::class);

        $this->post($manager->embedUrl($user, 'billing.subscribe', ['plan' => 'business']), ['interval' => 'monthly'])
            ->assertRedirect();

        $this->assertSame(['sub_a' => 'price_business_monthly'], TrackingCashierSubscription::$swapped);
        $this->assertSame(['sub_b'], TrackingCashierSubscription::$canceledImmediately);
    }

    public function test_ensure_subscribed_middleware_syncs_from_stripe_when_local_db_missed_webhook(): void
    {
        $this->seedCatalog();
        $user = SyncTestUser::create([
            'email' => 'gated@example.com',
            'stripe_id' => 'cus_gated_1',
        ]);

        FakeStripeSubscriptionsEndpoint::$byCustomer['cus_gated_1'] = [
            $this->stripeSubObject([
                'id' => 'sub_live_after_checkout',
                'price' => 'price_pro_monthly',
                'status' => 'active',
                'trial_end' => null,
            ]),
        ];

        $this->actingAs($user)
            ->get('/_test/gated')
            ->assertOk()
            ->assertSee('ok');

        $this->assertSame(1, $user->fresh()->subscriptions()->count());
    }
}

class SyncTestUser extends User
{
    use Billable;

    public static bool $checkoutOpened = false;

    protected $table = 'users';
    protected $guarded = [];

    public function getForeignKey()
    {
        return 'user_id';
    }

    public function newSubscription($type, $prices = []): \Laravel\Cashier\SubscriptionBuilder
    {
        self::$checkoutOpened = true;

        return parent::newSubscription($type, $prices);
    }
}

class FakeStripeClient extends \Stripe\StripeClient
{
    public FakeStripeSubscriptionsEndpoint $fakeSubscriptions;

    public function __construct()
    {
        parent::__construct('sk_test_fake');
        $this->fakeSubscriptions = new FakeStripeSubscriptionsEndpoint;
    }

    public function __get($name)
    {
        if ($name === 'subscriptions') {
            return $this->fakeSubscriptions;
        }

        if ($name === 'invoices') {
            return new FakeStripeInvoicesEndpoint;
        }

        return parent::__get($name);
    }
}

class FakeStripeInvoicesEndpoint
{
    public function all(array $params = []): object
    {
        return (object) ['data' => []];
    }
}

class FakeStripeSubscriptionsEndpoint
{
    /** @var array<string, array<int, object>> */
    public static array $byCustomer = [];

    public function all(array $params = []): object
    {
        $customer = $params['customer'] ?? '';

        return (object) [
            'data' => self::$byCustomer[$customer] ?? [],
        ];
    }
}

class TrackingCashierSubscription extends Subscription
{
    protected $table = 'subscriptions';

    public function getForeignKey()
    {
        return 'subscription_id';
    }

    /** @var array<string, string> */
    public static array $swapped = [];
    /** @var array<int, string> */
    public static array $skippedTrial = [];
    /** @var array<int, string> */
    public static array $enforcedPayment = [];
    /** @var array<int, string> */
    public static array $canceledGracefully = [];
    /** @var array<int, string> */
    public static array $canceledImmediately = [];

    public static function resetLog(): void
    {
        self::$swapped = [];
        self::$skippedTrial = [];
        self::$enforcedPayment = [];
        self::$canceledGracefully = [];
        self::$canceledImmediately = [];
    }

    public function skipTrial()
    {
        self::$skippedTrial[] = $this->stripe_id;

        return parent::skipTrial();
    }

    public function errorIfPaymentFails()
    {
        self::$enforcedPayment[] = $this->stripe_id;

        return parent::errorIfPaymentFails();
    }

    public function swapAndInvoice($prices, array $options = [])
    {
        $target = is_array($prices) ? reset($prices) : $prices;
        self::$swapped[$this->stripe_id] = $target;

        $this->fill([
            'stripe_price' => $target,
            'stripe_status' => 'active',
            'ends_at' => null,
        ])->save();

        return $this;
    }

    public function cancel()
    {
        self::$canceledGracefully[] = $this->stripe_id;

        $endsAt = $this->onTrial()
            ? $this->trial_ends_at
            : now()->addDays(30);

        $this->fill(['ends_at' => $endsAt])->save();

        return $this;
    }

    public function cancelNow()
    {
        self::$canceledImmediately[] = $this->stripe_id;

        $this->fill([
            'stripe_status' => 'canceled',
            'trial_ends_at' => null,
            'ends_at' => now(),
        ])->save();

        return $this;
    }
}
