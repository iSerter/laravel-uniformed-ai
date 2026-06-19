<?php

use Iserter\UniformedAI\Integration\RecordUsageMiddleware;
use Iserter\UniformedAI\Models\ServicePricing;
use Iserter\UniformedAI\Models\ServiceUsageLog;

beforeEach(function () {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.queue.enabled', false);
});

function mockPrompt(string $promptText = 'Hello', mixed $provider = 'openai', string $model = 'gpt-4.1'): object
{
    $prompt = new stdClass();
    $prompt->prompt = $promptText;
    $prompt->provider = $provider;
    $prompt->model = $model;
    return $prompt;
}

function mockResponse(int $promptTokens = 100, int $completionTokens = 50, string $text = 'Hi'): object
{
    $response = new stdClass();
    $response->text = $text;
    $response->usage = new stdClass();
    $response->usage->promptTokens = $promptTokens;
    $response->usage->completionTokens = $completionTokens;
    return $response;
}

it('logs usage when used as middleware', function () {
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-4.1',
        'unit' => '1M_tokens', 'input_cost_cents' => 250, 'output_cost_cents' => 1000,
        'currency' => 'USD', 'active' => true,
    ]);

    $middleware = RecordUsageMiddleware::make();
    $prompt = mockPrompt();
    $response = mockResponse();

    $result = $middleware->handle($prompt, fn ($p) => $response);

    expect($result)->toBe($response);

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('openai');
    expect($log->model)->toBe('gpt-4.1');
    expect($log->service_type)->toBe('chat');
    expect($log->service_operation)->toBe('prompt');
    expect($log->status)->toBe('success');
    expect($log->extra['usage']['prompt_tokens'])->toBe(100);
    expect($log->extra['usage']['completion_tokens'])->toBe(50);
    expect($log->extra['usage']['pricing_source'])->toContain('db:');
});

it('uses constructor overrides for provider and model', function () {
    $middleware = RecordUsageMiddleware::make(provider: 'anthropic', model: 'claude-sonnet-4-5-20250514');
    $prompt = mockPrompt(); // has openai/gpt-4.1 but overrides should win

    $middleware->handle($prompt, fn ($p) => mockResponse());

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('anthropic');
    expect($log->model)->toBe('claude-sonnet-4-5-20250514');
});

it('handles enum-style provider on prompt', function () {
    $middleware = RecordUsageMiddleware::make();

    $providerEnum = new class {
        public string $value = 'Anthropic';
    };
    $prompt = mockPrompt(provider: $providerEnum);

    $middleware->handle($prompt, fn ($p) => mockResponse());

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('anthropic');
});

it('handles prompt without expected properties', function () {
    $middleware = RecordUsageMiddleware::make();

    $prompt = new stdClass(); // no prompt, provider, model properties

    $middleware->handle($prompt, fn ($p) => mockResponse());

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('unknown');
    expect($log->model)->toBe('unknown');
    expect($log->status)->toBe('success');
});

it('uses custom extractUsage callable', function () {
    $middleware = RecordUsageMiddleware::make(
        extractUsage: fn ($r) => ['prompt_tokens' => 999, 'completion_tokens' => 888],
    );

    $middleware->handle(mockPrompt(), fn ($p) => new stdClass());

    $log = ServiceUsageLog::first();
    expect($log->extra['usage']['prompt_tokens'])->toBe(999);
    expect($log->extra['usage']['completion_tokens'])->toBe(888);
});

it('logs errors when next throws', function () {
    $middleware = RecordUsageMiddleware::make();

    expect(fn () => $middleware->handle(mockPrompt(), fn ($p) => throw new RuntimeException('API down')))
        ->toThrow(RuntimeException::class, 'API down');

    $log = ServiceUsageLog::first();
    expect($log->status)->toBe('error');
    expect($log->error_message)->toBe('API down');
    expect($log->error_class)->toBe(RuntimeException::class);
});

it('handles string prompt input', function () {
    $middleware = RecordUsageMiddleware::make(provider: 'openai', model: 'gpt-4.1');

    $middleware->handle('Just a string prompt', fn ($p) => mockResponse());

    $log = ServiceUsageLog::first();
    expect($log->request_payload['prompt'])->toBe('Just a string prompt');
    expect($log->status)->toBe('success');
});
