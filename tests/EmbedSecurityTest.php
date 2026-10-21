<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use LaraSaaS\LaraSaaS;
use LaraSaaS\Models\Plan;
use LaraSaaS\Support\BillingActions;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Subscription;

class EmbedSecurityTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('b', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        // Exercise standalone `embed` mode where `/app/*` routes do NOT exist at all.
        $app['config']->set('larasaas.integration.mode', 'embed');
        $app['config']->set('larasaas.user_model', EmbedTestUser::class);
        $app['config']->set('auth.providers.users.model', EmbedTestUser::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('password')->nullable();
            $table->timestamps();
        });

        (require __DIR__ . '/../database/migrations/2026_10_14_000000_create_larasaas_schema.php')->up();
    }

    private function createStarterPlan(array $overrides = []): Plan
    {
        return Plan::create(array_replace([
            'key' => 'starter',
            'name' => 'Starter',
            'description' => 'Starter plan',
            'active' => true,
            'highlight' => true,
            'badge' => 'Popular',
            'trial_days' => 14,
            'sort_order' => 1,
            'monthly' => ['price' => 9, 'stripe_id' => 'price_starter_monthly'],
            'yearly' => ['price' => 90, 'stripe_id' => 'price_starter_yearly'],
            'features' => ['uploads' => true, 'storage_gb' => 10],
        ], $overrides));
    }

    private function createUser(string $email = 'alice@example.com', ?string $password = 'hashed-secret-1'): EmbedTestUser
    {
        return EmbedTestUser::create(['email' => $email, 'password' => $password]);
    }

    public function test_signed_embed_link_for_user_a_is_rejected_when_user_b_is_logged_in(): void
    {
        $this->createStarterPlan();
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $manager = app(LaraSaaS::class);

        $alicePricing = $manager->embedUrl($alice, 'pricing');
        $aliceBilling = $manager->embedUrl($alice, 'billing');
        $aliceSubscribe = $manager->embedUrl($alice, 'billing.subscribe', ['plan' => 'starter']);
        $aliceCancel = $manager->embedUrl($alice, 'billing.cancel');
        $aliceResume = $manager->embedUrl($alice, 'billing.resume');
        $alicePortal = $manager->embedUrl($alice, 'billing.portal');

        // Unlogged cross-origin iframe / WebView works for Alice.
        $this->get($alicePricing)->assertOk();
        $this->get($aliceBilling)->assertOk();

        // Same user logged into session also works cleanly.
        $this->actingAs($alice)->get($alicePricing)->assertOk();
        $this->actingAs($alice)->get($aliceBilling)->assertOk();

        // Different user logged into session is strictly forbidden across every GET and POST.
        $this->actingAs($bob)->get($alicePricing)->assertForbidden();
        $this->actingAs($bob)->get($aliceBilling)->assertForbidden();
        $this->actingAs($bob)->post($aliceSubscribe, ['interval' => 'monthly'])->assertForbidden();
        $this->actingAs($bob)->post($aliceCancel)->assertForbidden();
        $this->actingAs($bob)->post($aliceResume)->assertForbidden();
        $this->actingAs($bob)->post($alicePortal)->assertForbidden();
    }

    public function test_embed_navigation_preserves_expiry_ceiling_instead_of_self_renewing_forever(): void
    {
        $this->createStarterPlan();
        $alice = $this->createUser();
        $manager = app(LaraSaaS::class);

        Carbon::setTestNow($start = Carbon::parse('2026-10-21 12:00:00'));

        try {
            $pricingUrl = $manager->embedUrl($alice, 'pricing', [], 5);
            parse_str((string) parse_url($pricingUrl, PHP_URL_QUERY), $initialQuery);
            $expectedExpires = (string) $start->copy()->addMinutes(5)->getTimestamp();
            $this->assertSame($expectedExpires, $initialQuery['expires']);

            // Open the embed 4 minutes later: rendered links must NOT reset TTL to +5m.
            Carbon::setTestNow($start->copy()->addMinutes(4));
            $html = $this->get($pricingUrl)->assertOk()->getContent();
            $this->assertNotFalse($html);

            // No long-lived return URLs exposed anywhere in HTML hidden inputs.
            $this->assertStringNotContainsString('name="success_url"', $html);
            $this->assertStringNotContainsString('name="cancel_url"', $html);

            preg_match_all('/expires=(\d+)/', $html, $matches);
            $this->assertNotEmpty($matches[1]);
            foreach ($matches[1] as $renderedExpires) {
                $this->assertSame($expectedExpires, $renderedExpires);
            }

            // Extract the rendered billing link and open it right after the original 5-minute ceiling expires.
            preg_match('/href="([^"]*\/embed\/billing\?[^"]+)"/', $html, $billingMatch);
            $this->assertNotEmpty($billingMatch[1]);
            $billingLink = html_entity_decode($billingMatch[1], ENT_QUOTES);

            Carbon::setTestNow($start->copy()->addMinutes(5)->addSecond());
            $this->get($billingLink)->assertForbidden();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_password_change_revokes_existing_signed_embed_links_and_strict_models_work(): void
    {
        $alice = $this->createUser('alice@example.com', 'old-password-hash');
        $manager = app(LaraSaaS::class);

        $billingUrl = $manager->embedUrl($alice, 'billing');
        $this->assertStringContainsString('ph=', $billingUrl);
        $this->get($billingUrl)->assertOk();

        // Removing `ph=` invalidates the signature; changing password revokes the signed token immediately.
        $stripped = preg_replace('/([?&])ph=[^&]+&?/', '$1', $billingUrl);
        $this->get((string) $stripped)->assertForbidden();

        $alice->update(['password' => 'new-password-hash']);
        $this->get($billingUrl)->assertForbidden();

        // Fresh embed URL after password update works again.
        $this->get($manager->embedUrl($alice->fresh(), 'billing'))->assertOk();

        // Partial Eloquent select (`User::select(['id'])`) under Model::shouldBeStrict() does not throw.
        Model::preventAccessingMissingAttributes(true);
        try {
            $partial = EmbedTestUser::query()->select(['id'])->findOrFail($alice->id);
            $partialUrl = $manager->embedUrl($partial, 'billing');
            $this->get($partialUrl)->assertOk();
        } finally {
            Model::preventAccessingMissingAttributes(false);
        }
    }

    public function test_embed_headers_set_referrer_policy_and_no_store_cache_control(): void
    {
        $this->createStarterPlan();
        $alice = $this->createUser();
        $manager = app(LaraSaaS::class);

        $publicRes = $this->get($manager->embedUrl());
        $publicRes->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('<meta name="referrer" content="no-referrer">', false);

        $billingRes = $this->get($manager->embedUrl($alice, 'billing'));
        $billingRes->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_portal_and_subscribe_enforce_signed_embed_return_urls_without_open_redirect_or_404(): void
    {
        $this->createStarterPlan();
        $alice = $this->createUser();
        $manager = app(LaraSaaS::class);

        $captured = new \ArrayObject;

        $this->app->bind(BillingActions::class, fn ($app) => new class($app->make(LaraSaaS::class), $captured) extends BillingActions
        {
            public function __construct(LaraSaaS $larasaas, private \ArrayObject $captured)
            {
                parent::__construct($larasaas);
            }

            public function subscribe(Request $request, string $plan, string $interval): array
            {
                $this->captured['checkout_success'] = $this->successUrl($request);
                $this->captured['checkout_cancel'] = $this->cancelUrl($request);

                return ['action' => 'checkout', 'url' => 'https://checkout.stripe.test/session_123'];
            }

            public function portalUrl(Request $request): string
            {
                $this->captured['portal_return'] = $this->successUrl($request);

                return 'https://billing.stripe.test/portal_123';
            }
        });

        // Attacker supplies malicious open-redirect targets in POST payload — controller overwrites them.
        $subscribeRes = $this->post($manager->embedUrl($alice, 'billing.subscribe', ['plan' => 'starter']), [
            'interval' => 'yearly',
            'success_url' => 'https://evil.example/phish',
            'cancel_url' => 'https://evil.example/cancel',
        ]);
        $subscribeRes->assertRedirect('https://checkout.stripe.test/session_123')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $this->assertStringNotContainsString('evil.example', $captured['checkout_success']);
        $this->assertStringNotContainsString('evil.example', $captured['checkout_cancel']);
        $this->get($captured['checkout_success'])->assertOk()->assertSee('Welcome aboard');
        $this->get($captured['checkout_cancel'])->assertOk()->assertSee('Checkout canceled');

        // Standalone `embed` mode has NO `/app/billing` route — portal return URL must resolve cleanly to `/embed/billing`.
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('larasaas.billing.index'));
        $portalRes = $this->post($manager->embedUrl($alice, 'billing.portal'), [
            'success_url' => 'https://evil.example/portal',
        ]);
        $portalRes->assertRedirect('https://billing.stripe.test/portal_123');
        $this->assertStringContainsString('/embed/billing', $captured['portal_return']);
        $this->get($captured['portal_return'])->assertOk()->assertDontSee('Welcome aboard');
    }

    public function test_embed_pricing_supports_same_plan_interval_switch_in_frame_swap_and_accurate_trial_copy(): void
    {
        $this->createStarterPlan();
        $alice = $this->createUser();
        $manager = app(LaraSaaS::class);

        // Unsubscribed user: sees trial copy and `target="_top"` checkout form.
        $guestLikeUserHtml = $this->get($manager->embedUrl($alice, 'pricing'))->assertOk()->getContent();
        $this->assertStringContainsString('14-day free trial', (string) $guestLikeUserHtml);
        $this->assertStringContainsString('target="_top"', (string) $guestLikeUserHtml);

        // Active monthly subscriber: Monthly tab shows disabled "Current plan", Yearly tab enables "Switch plan" in-frame!
        EmbedTestUser::$subscriptionsById[$alice->id] = new Subscription([
            'stripe_price' => 'price_starter_monthly',
            'stripe_status' => 'active',
        ]);

        try {
            $monthlyHtml = $this->get($manager->embedUrl($alice, 'pricing', ['interval' => 'monthly']))->assertOk()->getContent();
            $this->assertStringContainsString('Current plan', (string) $monthlyHtml);
            $this->assertStringContainsString('<button type="button" class="ls-btn" disabled>', (string) $monthlyHtml);
            $this->assertStringNotContainsString('14-day free trial', (string) $monthlyHtml);
            $this->assertStringNotContainsString('target="_top"', (string) $monthlyHtml);

            $yearlyHtml = $this->get($manager->embedUrl($alice, 'pricing', ['interval' => 'yearly']))->assertOk()->getContent();
            $this->assertStringContainsString('Switch plan', (string) $yearlyHtml);
            $this->assertStringNotContainsString('<button type="button" class="ls-btn" disabled>', (string) $yearlyHtml);
            $this->assertStringNotContainsString('14-day free trial', (string) $yearlyHtml);
        } finally {
            EmbedTestUser::$subscriptionsById = [];
        }
    }
}

class EmbedTestUser extends User
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
