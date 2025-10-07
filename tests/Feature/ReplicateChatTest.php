<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Services\Chat\ChatManager;

it('sends chat via Replicate driver (prediction)', function() {
    config()->set('uniformed-ai.defaults.chat', 'replicate');
    config()->set('uniformed-ai.providers.replicate.api_key', 'rep-key');
    config()->set('uniformed-ai.providers.replicate.base_url', 'https://api.replicate.com/v1');
    config()->set('uniformed-ai.providers.replicate.chat.model', 'replicate/hello-world:123');

    Http::fake([
        'api.replicate.com/*' => Http::response([
            'id' => 'pred-1',
            'status' => 'succeeded',
            'output' => 'Hi there from Replicate',
        ], 200)
    ]);

    $resp = app(ChatManager::class)->send(new ChatRequest([
        new ChatMessage('user', 'Hi')
    ], temperature: 0.5));

    expect($resp->content)->toBe('Hi there from Replicate');
});
