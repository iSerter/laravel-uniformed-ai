<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Services\Chat\ChatManager;

it('streams chat deltas from OpenAI driver', function() {
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');

    $sseBody = implode("\n\n", [
        'data: '. json_encode(['choices' => [['delta' => ['content' => 'Hello']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => ' World']]]]),
        'data: '. json_encode(['choices' => [['delta' => ['content' => '!']]]]),
        '',
    ]);

    Http::fake([
        'api.openai.com/*' => Http::response($sseBody, 200, ['Content-Type' => 'text/event-stream'])
    ]);

    $manager = app(ChatManager::class);
    $gen = $manager->stream(new ChatRequest([
        new ChatMessage('user', 'Hi')
    ]));

    $collected = '';
    foreach ($gen as $delta) { $collected .= $delta; }

    expect($collected)->toBe('Hello World!');
});
