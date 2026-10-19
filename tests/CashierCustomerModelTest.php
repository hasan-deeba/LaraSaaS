<?php

namespace LaraSaaS\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Log;
use LaraSaaS\LaraSaaS;
use Laravel\Cashier\Billable;
use Laravel\Cashier\Cashier;

/**
 * Regression for the "works everywhere except the Stripe webhook" trap:
 *
 * Cashier resolves webhook customers through its OWN static customer model
 * (`Cashier::findBillable()` → `class_uses_recursive()`), which defaults to a
 * hardcoded `App\Models\User` and never reads `larasaas.user_model`. Hosts whose
 * user model lives in another namespace got
 * `class_parents(): Class App\Models\User does not exist` plus HTTP 500 on
 * `customer.subscription.*` and `invoice.*` events.
 *
 * LaraSaaS therefore points Cashier at the configured billable model itself.
 *
 * ⚠ No class named `App\Models\User` may ever be declared in the test suite:
 *   its absence IS the scenario under test.
 */
class CashierCustomerModelTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('larasaas.integration.mode', 'all');
        $app['config']->set('larasaas.user_model', NamespacedBillingUser::class);
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        // Inspectable log sink for the "fails loudly" assertions: a Monolog
        // TestHandler keeps every record in memory (Laravel's own Logger
        // wrapper exposes neither driver() nor getMonolog()).
        $app['config']->set('logging.default', 'capture');
        $app['config']->set('logging.channels.capture', [
            'driver' => 'monolog',
            'handler' => \Monolog\Handler\TestHandler::class,
        ]);
    }

    public function test_cashier_is_pointed_at_the_configured_user_model_on_register(): void
    {
        // The provider already ran while the application was created.
        $this->assertSame(NamespacedBillingUser::class, Cashier::$customerModel);
        $this->assertSame(NamespacedBillingUser::class, app(LaraSaaS::class)->cashierCustomerModel());

        // Mirrored into the config repository as well (config:cache friendly).
        $this->assertSame(NamespacedBillingUser::class, config('cashier.model'));
    }

    public function test_cashier_is_pointed_at_the_workspace_model_for_workspace_billing(): void
    {
        config([
            'larasaas.billing.billable' => 'workspace',
            'larasaas.billing.workspace_model' => CashierWorkspace::class,
        ]);

        $this->assertTrue(app(LaraSaaS::class)->syncCashierCustomerModel());
        $this->assertSame(CashierWorkspace::class, Cashier::$customerModel);
        $this->assertSame(CashierWorkspace::class, config('cashier.model'));
    }

    public function test_a_host_that_chooses_later_keeps_the_final_say(): void
    {
        // Package providers register and boot before the host's, so an explicit
        // Cashier::useCustomerModel() in AppServiceProvider::boot() still wins.
        Cashier::useCustomerModel(CashierWorkspace::class);

        $this->assertSame(CashierWorkspace::class, Cashier::$customerModel);

        // …and LaraSaaS never fights it during the request: the webhook lookup
        // uses whatever Cashier points at.
        $this->assertSame(CashierWorkspace::class, app(LaraSaaS::class)->cashierCustomerModel());
    }

    public function test_an_unloadable_customer_model_is_repaired(): void
    {
        Cashier::useCustomerModel('App\\Models\\RenamedAway\\User');

        $this->assertTrue(app(LaraSaaS::class)->syncCashierCustomerModel());
        $this->assertSame(NamespacedBillingUser::class, Cashier::$customerModel);
    }

    public function test_the_webhook_lookup_recovers_from_a_clobbered_customer_model(): void
    {
        Cashier::useCustomerModel('App\\Models\\User');

        $controller = new \LaraSaaS\Http\Controllers\StripeWebhookController;
        $lookup = new \ReflectionMethod($controller, 'getUserByStripeId');
        $lookup->setAccessible(true);

        // A null Stripe ID keeps Cashier away from the database while still
        // exercising the model guard: recovering means no class-loading error.
        $this->assertNull($lookup->invoke($controller, null));
        $this->assertSame(NamespacedBillingUser::class, Cashier::$customerModel);
    }

    public function test_the_webhook_fails_loudly_when_no_billable_model_can_be_loaded(): void
    {
        // Both the Cashier static and the configured billable are unloadable —
        // nothing can be recovered, so the lookup must explain itself instead
        // of dying inside class_parents().
        $this->assertFalse(class_exists('App\\Models\\User'));
        config(['larasaas.user_model' => 'App\\Models\\GhostBillable']);
        Cashier::$customerModel = '';

        $controller = new \LaraSaaS\Http\Controllers\StripeWebhookController;
        $lookup = new \ReflectionMethod($controller, 'getUserByStripeId');
        $lookup->setAccessible(true);

        $thrown = null;

        try {
            $lookup->invoke($controller, 'cus_test');
        } catch (\RuntimeException $e) {
            $thrown = $e;
        }

        // Throwing keeps the webhook at HTTP 500, so Stripe retries the event
        // and replays it once the model config is fixed — instead of
        // acknowledging it and losing the subscription.
        $this->assertInstanceOf(\RuntimeException::class, $thrown);
        $this->assertStringContainsString(
            'Cashier customer model [App\\Models\\GhostBillable] does not exist',
            $thrown->getMessage(),
        );

        // One actionable log line replaces the class-loading error.
        $logged = $this->loggedMessages();
        $this->assertStringContainsString('could not resolve the billable customer', $logged);
        $this->assertStringContainsString('App\\Models\\GhostBillable', $logged);
    }

    public function test_the_doctor_reports_a_customer_model_that_disagrees_with_the_billable(): void
    {
        Cashier::useCustomerModel(CashierWorkspace::class);

        $report = $this->doctorReport();
        $this->assertFalse($report['cashier_customer_model']['ok']);
        $this->assertStringContainsString(NamespacedBillingUser::class, $report['cashier_customer_model']['message']);
        $this->assertStringContainsString(CashierWorkspace::class, $report['cashier_customer_model']['message']);

        // Aligned again: no findings about the mapping or the trait.
        Cashier::useCustomerModel(NamespacedBillingUser::class);
        $report = $this->doctorReport();
        $this->assertTrue($report['cashier_customer_model']['ok']);
        $this->assertTrue($report['cashier_billable_trait']['ok']);

        // A billable without Cashier's trait is its own actionable finding.
        config(['larasaas.user_model' => NamespacedUserWithoutTrait::class]);
        Cashier::useCustomerModel(NamespacedUserWithoutTrait::class);
        $report = $this->doctorReport();
        $this->assertFalse($report['cashier_billable_trait']['ok']);
        $this->assertStringContainsString('Billable trait', $report['cashier_billable_trait']['message']);
    }

    public function test_the_doctor_reports_an_unloadable_billable_model_without_a_database(): void
    {
        config(['larasaas.user_model' => 'App\\Models\\AccessManagement\\Missing']);
        config(['database.default' => 'unconfigured']);

        // Schema-dependent checks stay silent; the model checks still speak up.
        $issues = app(\LaraSaaS\Support\SetupDoctor::class)->issues();

        $this->assertStringContainsString('does not exist', implode("\n", $issues));
        $this->assertStringContainsString('Cashier has nothing to sync', implode("\n", $issues));
    }

    /**
     * Everything written to the in-memory capture channel. Only uses
     * Monolog-level methods (`__call` forwards them), so it survives the
     * different Illuminate\Log wrappers across Laravel 10-12 and Monolog 2-3.
     */
    private function loggedMessages(): string
    {
        $messages = [];

        try {
            // No method_exists() guard here: getHandlers() reaches Monolog
            // through the wrapper's __call(), so it is invisible to reflection.
            $handlers = Log::channel('capture')->getHandlers();
        } catch (\Throwable $e) {
            return '';
        }

        foreach ($handlers as $handler) {
            if (! $handler instanceof \Monolog\Handler\TestHandler) {
                continue;
            }

            foreach ($handler->getRecords() as $record) {
                $messages[] = (string) (is_array($record) ? ($record['message'] ?? '') : $record->message);
            }
        }

        return implode("\n", $messages);
    }

    /** @return \Illuminate\Support\Collection<string, array{check: string, ok: bool, level: string, message: string}> */
    private function doctorReport(): \Illuminate\Support\Collection
    {
        return collect(app(\LaraSaaS\Support\SetupDoctor::class)->report())->keyBy('check');
    }
}

/** The host's real user model: namespaced, Billable, never `App\Models\User`. */
class NamespacedBillingUser extends User
{
    use Billable;

    protected $table = 'users';
    protected $guarded = [];
}

class NamespacedUserWithoutTrait extends User
{
    protected $table = 'users';
    protected $guarded = [];
}

class CashierWorkspace extends Model
{
    use Billable;

    protected $table = 'larasaas_workspaces';
    protected $guarded = [];
}
