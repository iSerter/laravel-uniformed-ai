<?php

namespace Iserter\UniformedAI\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Iserter\UniformedAI\UniformedAIServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [UniformedAIServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // load migrations inside package
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
