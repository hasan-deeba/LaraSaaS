<?php

namespace LaraSaaS\Tests;

use LaraSaaS\LaraSaaSServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /** Cashier keeps its customer model in a process-wide static. */
    private ?string $cashierCustomerModelBefore = null;

    protected function getPackageProviders($app): array
    {
        return [PennantServiceProvider::class, CashierServiceProvider::class, LaraSaaSServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larasaas.integration.mode', 'custom');
        $app['config']->set('pennant.default', 'array');
    }

    protected function setUp(): void
    {
        $this->cashierCustomerModelBefore = class_exists(Cashier::class) ? Cashier::$customerModel : null;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        // LaraSaaS points Cashier at the configured billable; keep that from
        // leaking into the next test's expectations.
        if (class_exists(Cashier::class) && $this->cashierCustomerModelBefore !== null) {
            Cashier::$customerModel = $this->cashierCustomerModelBefore;
        }

        parent::tearDown();
    }
}
