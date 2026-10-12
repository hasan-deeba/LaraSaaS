<?php

namespace LaraSaaS\Tests;

use LaraSaaS\LaraSaaSServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [LaraSaaSServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('larasaas.integration.mode', 'custom');
    }
}
