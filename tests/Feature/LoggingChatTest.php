<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatMessage};
use Iserter\UniformedAI\Models\ServiceUsageLog;
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Exceptions\ProviderException;

beforeEach(function() {
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'sk-testSECRETKEY1234567890123');
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.stream.store_chunks', true);
});

it('logs successful chat send with sanitized request/response', function() {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Hello World']] ],
        ], 200)
    ]);

    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([
        new ChatMessage('user', 'My secret key is sk-EXPOSEDSHOULDNOTSHOWTHIS123456'),
    ]));

    $log = ServiceUsageLog::latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->status)->toBe('success');
    expect($log->service_operation)->toBe('send');
    expect($log->response_payload['content'] ?? null)->toContain('Hello World');
    // redaction mask applied (value replaced entirely)
    $json = json_encode($log->request_payload);
    expect($json)->not->toContain('sk-EXPOSEDSHOULDNOTSHOWTHIS');
});

it('logs error with exception metadata', function() {
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'Upstream boom']], 500)
    ]);

    $manager = app(ChatManager::class);
    expect(fn() => $manager->send(new ChatRequest([
        new ChatMessage('user', 'Trigger error')
    ])))->toThrow(ProviderException::class);

    $log = ServiceUsageLog::latest('id')->first();
    expect($log->status)->toBe('error');
    expect($log->error_class)->toBe(ProviderException::class);
    expect($log->error_message)->toBe('Upstream boom');
    expect($log->http_status)->toBe(500);
});

it('logs streaming accumulation with chunks', function() {
    $sseBody = implode("\n\n", [
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'A']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'B']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'C']]]]),
        '',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response($sseBody, 200, ['Content-Type' => 'text/event-stream'])
    ]);

    $manager = app(ChatManager::class);
    $gen = $manager->stream(new ChatRequest([
        new ChatMessage('user', 'Stream please')
    ]));
    foreach ($gen as $d) { /* exhaust */ }

    $log = ServiceUsageLog::latest('id')->first();
    expect($log->service_operation)->toBe('stream');
    expect($log->status)->toBe('success');
    expect($log->response_payload['content'])->toBe('ABC');
    expect($log->stream_chunks)->toHaveCount(3);
});

it('dispatches persistence job when queue enabled', function() {
    \Illuminate\Support\Facades\Bus::fake();
    config()->set('uniformed-ai.logging.queue.enabled', true);
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Queued Hello']] ],
        ], 200)
    ]);

    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([
        new ChatMessage('user', 'Hi queued')
    ]));

    \Illuminate\Support\Facades\Bus::assertDispatched(\Iserter\UniformedAI\Logging\PersistServiceUsageLogJob::class, function($job) {
        // Ensure redaction happened (api key masked) and operation metadata present
        $payloadJson = json_encode($job->payload);
        return str_contains($payloadJson, 'service_type') && !str_contains($payloadJson, 'sk-testSECRETKEY1234567890123');
    });

    // Since queued, row might not yet exist
    expect(\Iserter\UniformedAI\Models\ServiceUsageLog::count())->toBe(0);

    // Run job manually to simulate worker
    $dispatched = [];
    \Illuminate\Support\Facades\Bus::assertDispatched(\Iserter\UniformedAI\Logging\PersistServiceUsageLogJob::class, function($job) use (&$dispatched) { $dispatched[] = $job; return true; });
    foreach ($dispatched as $job) { $job->handle(); }

    expect(\Iserter\UniformedAI\Models\ServiceUsageLog::count())->toBe(1);
});
