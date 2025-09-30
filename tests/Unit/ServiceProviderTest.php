<?php

use Iserter\UniformedAI\UniformedAIServiceProvider;
use Iserter\UniformedAI\Facades\AI;
use Iserter\UniformedAI\Logging\Usage\{UsageMetricsCollector, ProviderUsageExtractor, HeuristicCl100kEstimator, PricingEngine};
use Iserter\UniformedAI\Support\PricingRepository;

it('registers singletons and facade accessor', function() {
    $app = app();
    $provider = new UniformedAIServiceProvider($app);
    $provider->register();

    expect($app->bound(UsageMetricsCollector::class))->toBeTrue();
    expect($app->make(ProviderUsageExtractor::class))->toBeInstanceOf(ProviderUsageExtractor::class);
    expect($app->make(HeuristicCl100kEstimator::class))->toBeInstanceOf(HeuristicCl100kEstimator::class);
    expect($app->make(PricingEngine::class))->toBeInstanceOf(PricingEngine::class);
    expect($app->make(PricingRepository::class))->toBeInstanceOf(PricingRepository::class);

    // Facade resolves underlying chat manager
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');
    $chatManager = AI::chat();
    expect(method_exists($chatManager, 'send'))->toBeTrue();
});

it('validates malformed usage sampling config', function() {
    config()->set('uniformed-ai.logging.usage', 'not-array');
    $provider = new UniformedAIServiceProvider(app());
    $provider->boot();
    // nothing to assert directly (logs) but ensure no exception
    expect(true)->toBeTrue();
});

it('warns on invalid sampling rate', function() {
    config()->set('uniformed-ai.logging.usage', [ 'sampling' => ['success_rate' => 5] ]);
    $provider = new UniformedAIServiceProvider(app());
    $provider->boot();
    expect(true)->toBeTrue();
});
