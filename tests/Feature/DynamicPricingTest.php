<?php

use Iserter\UniformedAI\Logging\Usage\PricingEngine;
use Iserter\UniformedAI\Models\ServicePricing;
use Iserter\UniformedAI\Models\ServicePricingTier;
use Iserter\UniformedAI\Support\PricingRepository;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

it('calculates cost based on tiers', function () {
    $pricing = ServicePricing::create([
        'provider' => 'test-provider',
        'model_pattern' => 'test-model',
        'service_type' => 'chat',
        'unit' => '1M_tokens',
        'input_cost_cents' => 1000,
        'output_cost_cents' => 2000,
        'currency' => 'USD',
        'active' => true,
    ]);

    ServicePricingTier::create([
        'service_pricing_id' => $pricing->id,
        'min_units' => 0,
        'max_units' => 128000,
        'input_cost_cents' => 100,
        'output_cost_cents' => 200,
    ]);

    ServicePricingTier::create([
        'service_pricing_id' => $pricing->id,
        'min_units' => 128001,
        'max_units' => null,
        'input_cost_cents' => 500,
        'output_cost_cents' => 1000,
    ]);

    $repo = app(PricingRepository::class);
    $engine = new PricingEngine($repo);

    // Case 1: Within first tier (total tokens = 50,000 + 50,000 = 100,000)
    // 100,000 is <= 128,000. Tier 1 matches.
    $result1 = $engine->price('test-provider', 'test-model', 'chat', 50000, 50000);
    expect($result1['input_cost_cents'])->toBe(5); // (50,000 / 1M) * 100 = 5
    expect($result1['output_cost_cents'])->toBe(10); // (50,000 / 1M) * 200 = 10
    
    // Case 2: Within second tier (total tokens = 200,000)
    // 200,000 is > 128,000. Tier 2 matches.
    $result2 = $engine->price('test-provider', 'test-model', 'chat', 100000, 100000); 
    expect($result2['input_cost_cents'])->toBe(50); // (100,000 / 1M) * 500 = 50
    expect($result2['output_cost_cents'])->toBe(100); // (100,000 / 1M) * 1000 = 100
});

it('falls back to base pricing if no tiers match', function () {
    $pricing = ServicePricing::create([
        'provider' => 'test-provider',
        'model_pattern' => 'test-model-no-tiers',
        'service_type' => 'chat',
        'unit' => '1M_tokens',
        'input_cost_cents' => 1000,
        'output_cost_cents' => 2000,
        'currency' => 'USD',
        'active' => true,
    ]);

    $repo = app(PricingRepository::class);
    $engine = new PricingEngine($repo);

    $result = $engine->price('test-provider', 'test-model-no-tiers', 'chat', 500000, 500000);
    expect($result['input_cost_cents'])->toBe(500); // (500k/1M) * 1000 = 500
    expect($result['output_cost_cents'])->toBe(1000); // (500k/1M) * 2000 = 1000
});
