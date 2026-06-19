<?php

use Iserter\UniformedAI\Integration\UsageLogger;
use Iserter\UniformedAI\Logging\Usage\PricingEngine;
use Iserter\UniformedAI\Models\ServicePricing;
use Iserter\UniformedAI\Models\ServiceUsageLog;
use Iserter\UniformedAI\Support\PricingRepository;

beforeEach(function () {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.queue.enabled', false);
});

function makeLogger(): UsageLogger
{
    return new UsageLogger(new PricingEngine(new PricingRepository()));
}

it('records a successful operation with usage and cost', function () {
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-4.1',
        'unit' => '1M_tokens', 'input_cost_cents' => 250, 'output_cost_cents' => 1000,
        'currency' => 'USD', 'active' => true,
    ]);

    $logger = makeLogger();

    $result = $logger->record(
        provider: 'openai',
        model: 'gpt-4.1',
        serviceType: 'chat',
        operation: 'prompt',
        request: ['messages' => [['role' => 'user', 'content' => 'Hello']]],
        execute: fn () => ['text' => 'Hi there!', 'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5]],
        extractUsage: fn ($r) => $r['usage'],
        extractResponse: fn ($r) => ['text' => $r['text']],
    );

    expect($result)->toHaveKey('text', 'Hi there!');

    $log = ServiceUsageLog::first();
    expect($log)->not->toBeNull();
    expect($log->provider)->toBe('openai');
    expect($log->model)->toBe('gpt-4.1');
    expect($log->service_type)->toBe('chat');
    expect($log->service_operation)->toBe('prompt');
    expect($log->status)->toBe('success');
    expect($log->extra['usage']['prompt_tokens'])->toBe(10);
    expect($log->extra['usage']['completion_tokens'])->toBe(5);
    expect($log->extra['usage']['total_tokens'])->toBe(15);
    expect($log->extra['usage']['pricing_source'])->toContain('db:');
});

it('records a failed operation', function () {
    $logger = makeLogger();

    expect(fn () => $logger->record(
        provider: 'anthropic',
        model: 'claude-sonnet-4-5-20250514',
        serviceType: 'chat',
        operation: 'prompt',
        request: ['messages' => []],
        execute: fn () => throw new RuntimeException('API error'),
    ))->toThrow(RuntimeException::class, 'API error');

    $log = ServiceUsageLog::first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('error');
    expect($log->error_message)->toBe('API error');
    expect($log->error_class)->toBe(RuntimeException::class);
});

it('works without extractUsage callback', function () {
    $logger = makeLogger();

    $result = $logger->record(
        provider: 'openai',
        model: 'gpt-4.1',
        serviceType: 'chat',
        operation: 'prompt',
        request: ['messages' => []],
        execute: fn () => 'done',
    );

    expect($result)->toBe('done');

    $log = ServiceUsageLog::first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('success');
    expect($log->extra)->toBeNull();
});

it('resolves provider aliases', function () {
    config()->set('uniformed-ai.provider_aliases', ['open-ai' => 'openai']);

    $logger = makeLogger();

    $logger->record(
        provider: 'open-ai',
        model: 'gpt-4.1',
        serviceType: 'chat',
        operation: 'prompt',
        request: [],
        execute: fn () => 'ok',
    );

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('openai');
});

it('records a streaming operation', function () {
    $logger = makeLogger();

    $stream = (function () {
        yield 'Hello';
        yield ' ';
        yield 'world';
    })();

    $chunks = [];
    foreach ($logger->recordStream(
        provider: 'openai',
        model: 'gpt-4.1',
        serviceType: 'chat',
        operation: 'stream',
        request: ['messages' => [['role' => 'user', 'content' => 'Hi']]],
        stream: $stream,
        extractUsage: fn (string $content) => [
            'prompt_tokens' => 5,
            'completion_tokens' => strlen($content),
        ],
    ) as $chunk) {
        $chunks[] = $chunk;
    }

    expect($chunks)->toBe(['Hello', ' ', 'world']);

    $log = ServiceUsageLog::first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('success');
    expect($log->service_operation)->toBe('stream');
    expect($log->extra['usage']['prompt_tokens'])->toBe(5);
    expect($log->extra['usage']['completion_tokens'])->toBe(11); // strlen('Hello world')
});

it('records a failed streaming operation', function () {
    $logger = makeLogger();

    $stream = (function () {
        yield 'partial';
        throw new RuntimeException('Stream interrupted');
    })();

    $chunks = [];
    expect(function () use ($logger, $stream, &$chunks) {
        foreach ($logger->recordStream(
            provider: 'openai',
            model: 'gpt-4.1',
            serviceType: 'chat',
            operation: 'stream',
            request: [],
            stream: $stream,
        ) as $chunk) {
            $chunks[] = $chunk;
        }
    })->toThrow(RuntimeException::class, 'Stream interrupted');

    expect($chunks)->toBe(['partial']);

    $log = ServiceUsageLog::first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('error');
    expect($log->error_message)->toBe('Stream interrupted');
});

it('gracefully handles extractUsage exceptions', function () {
    $logger = makeLogger();

    $result = $logger->record(
        provider: 'openai',
        model: 'gpt-4.1',
        serviceType: 'chat',
        operation: 'prompt',
        request: [],
        execute: fn () => 'ok',
        extractUsage: fn ($r) => throw new RuntimeException('extraction failed'),
    );

    // Should not throw — the operation result is returned even if usage extraction fails
    expect($result)->toBe('ok');

    $log = ServiceUsageLog::first();
    expect($log->status)->toBe('success');
});

it('can be resolved from the container', function () {
    $logger = app(UsageLogger::class);
    expect($logger)->toBeInstanceOf(UsageLogger::class);
});
