<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Managers\ChatManager;

it('sends chat via OpenAI driver', function() {
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                ['message' => ['content' => 'Hello from OpenAI']]
            ]
        ], 200)
    ]);

    $manager = app(ChatManager::class);
    $resp = $manager->send(new ChatRequest([
        new ChatMessage('user', 'Hi')
    ]));

    expect($resp->content)->toBe('Hello from OpenAI');
});
