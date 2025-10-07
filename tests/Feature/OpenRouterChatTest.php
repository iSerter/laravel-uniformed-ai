<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('sends chat via OpenRouter driver with temperature and max tokens', function() {
    config()->set('uniformed-ai.defaults.chat', 'openrouter');
    config()->set('uniformed-ai.providers.openrouter.api_key', 'or-key');
    config()->set('uniformed-ai.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Hello from OR']] ]
        ], 200)
    ]);

    $req = new ChatRequest([
        new ChatMessage('user', 'Hi OR')
    ], temperature: 0.7, maxTokens: 100);

    $resp = app(ChatManager::class)->send($req);
    expect($resp->content)->toBe('Hello from OR');
});

it('streams chat via OpenRouter driver', function() {
    config()->set('uniformed-ai.defaults.chat', 'openrouter');
    config()->set('uniformed-ai.providers.openrouter.api_key', 'or-key');
    config()->set('uniformed-ai.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');

    $sseBody = implode("\n\n", [
        ': OPENROUTER PROCESSING',
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'Hel']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'lo']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => ' OR']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => '!'], 'finish_reason' => 'stop']]]),
        'data: [DONE]',
        '',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response($sseBody, 200, ['Content-Type' => 'text/event-stream'])
    ]);

    $gen = app(ChatManager::class)->stream(new ChatRequest([
        new ChatMessage('user', 'Hi OR stream')
    ]));

    $collected = '';
    foreach ($gen as $d) { $collected .= $d; }

    expect($collected)->toBe('Hello OR!');
});

it('handles mid-stream error event from OpenRouter', function() {
    config()->set('uniformed-ai.defaults.chat', 'openrouter');
    config()->set('uniformed-ai.providers.openrouter.api_key', 'or-key');
    config()->set('uniformed-ai.providers.openrouter.base_url', 'https://openrouter.ai/api/v1');

    $sseBody = implode("\n\n", [
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'Partial']]]]),
        'data: '. json_encode([
            'id' => 'cmpl-err',
            'error' => [ 'code' => 'server_error', 'message' => 'Provider disconnected unexpectedly' ],
            'choices' => [[ 'delta' => ['content' => ''], 'finish_reason' => 'error' ]]
        ]),
        '',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response($sseBody, 200, ['Content-Type' => 'text/event-stream'])
    ]);

    $gen = app(ChatManager::class)->stream(new ChatRequest([
        new ChatMessage('user', 'Trigger error')
    ]));

    $received = '';
    $thrown = null;
    try {
        foreach ($gen as $delta) { $received .= $delta; }
    } catch (ProviderException $e) {
        $thrown = $e;
    }

    expect($received)->toBe('Partial');
    expect($thrown)->not()->toBeNull();
    expect($thrown->getMessage())->toContain('Provider disconnected');
});
