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
}
