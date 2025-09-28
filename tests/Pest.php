<?php

use Iserter\UniformedAI\UniformedAIServiceProvider;

/**
 * Pest configuration
 */

uses(Orchestra\Testbench\TestCase::class)->in('.');

function getPackageProviders($app)
{
    return [UniformedAIServiceProvider::class];
}
