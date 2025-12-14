<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Support\{RateLimiter, PricingRepository, CacheRepository, HttpClientFactory};
use Iserter\UniformedAI\Models\ServicePricing;

beforeEach(function() {
    Cache::flush();
});

it('throttles after exceeding limit per minute', function() {
    $rl = new RateLimiter();
    $rl->throttle('openai', 2);
    $rl->throttle('openai', 2);
    expect(fn() => $rl->throttle('openai', 2))->toThrow(\Iserter\UniformedAI\Exceptions\RateLimitException::class);
});

it('no throttle when limit <= 0', function() {
    $rl = new RateLimiter();
    $rl->throttle('openai', 0); // should noop
    expect(true)->toBeTrue();
});

it('resolves pricing precedence exact service over global', function() {
    $now = now();
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => null, 'model_pattern' => 'gpt-4o', 'unit' => '1K_tokens',
        'input_cost_cents' => 11, 'output_cost_cents' => 22, 'currency' => 'USD', 'active' => true,
    ]);
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-4o', 'unit' => '1K_tokens',
        'input_cost_cents' => 33, 'output_cost_cents' => 44, 'currency' => 'USD', 'active' => true,
    ]);

    $repo = new PricingRepository();
    $pricing = $repo->resolve('openai', 'gpt-4o', 'chat');
    expect($pricing['input'])->toBe(33);
});

it('resolves wildcard patterns when exact missing', function() {
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-99*', 'unit' => '1K_tokens',
        'input_cost_cents' => 10, 'output_cost_cents' => 20, 'currency' => 'USD', 'active' => true,
    ]);
    $repo = new PricingRepository();
    $pricing = $repo->resolve('openai', 'gpt-99-experimental', 'chat');
    expect($pricing['pattern'])->toBe('gpt-99*');
});

it('returns null pricing when no match', function() {
    $repo = new PricingRepository();
    $pricing = $repo->resolve('openai', 'unknown-model', 'chat');
    expect($pricing)->toBeNull();
});

it('caches pricing lookups', function() {
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-cache', 'unit' => '1K_tokens',
        'input_cost_cents' => 1, 'output_cost_cents' => 2, 'currency' => 'USD', 'active' => true,
    ]);
    $repo = new PricingRepository();
    $first = $repo->resolve('openai', 'gpt-cache', 'chat');
    ServicePricing::where('model_pattern','gpt-cache')->update(['input_cost_cents' => 999]);
    $second = $repo->resolve('openai', 'gpt-cache', 'chat');
    expect($second['input'])->toBe(1); // cached value remains
});

it('wraps http client with base url, token and retry', function() {
    config()->set('uniformed-ai.http.timeout', 2.5);
    config()->set('uniformed-ai.http.retries', 1);
    config()->set('uniformed-ai.http.retry_delay_ms', 10);
    Http::fake(['api.example.com/*' => Http::response(['ok' => true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://api.example.com/', 'api_key' => 'abc']);
    $resp = $client->get('/status');
    expect($resp->json('ok'))->toBeTrue();
});

it('builds url helper correctly', function() {
    expect(HttpClientFactory::url(['base_url' => 'https://x.test/'], '/v1/test'))->toBe('https://x.test/v1/test');
    expect(HttpClientFactory::url([], 'plain/path'))->toBe('plain/path');
});

it('cache repository delegates to facade', function() {
    $repo = new CacheRepository();
    $value = $repo->remember('k', 10, fn() => 'v');
    expect($value)->toBe('v');
});
