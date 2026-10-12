<?php

namespace LaraSaaS\Tests;

use LaraSaaS\LaraSaaSServiceProvider;
use Laravel\Cashier\CashierServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PennantServiceProvider::class, CashierServiceProvider::class, LaraSaaSServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larasaas.integration.mode', 'custom');
        $app['config']->set('pennant.default', 'array');
    }
}
