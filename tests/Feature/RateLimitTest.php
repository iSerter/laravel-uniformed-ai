<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Iserter\UniformedAI\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Managers\ChatManager;
use Iserter\UniformedAI\Exceptions\RateLimitException;

it('enforces rate limiting on chat', function() {
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');
    config()->set('uniformed-ai.rate_limit.openai', 1); // only 1 per minute

    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Hello']] ]
        ], 200)
    ]);

    Cache::flush();

    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([ new ChatMessage('user', 'first') ]));

    expect(fn() => $manager->send(new ChatRequest([ new ChatMessage('user', 'second') ])))
        ->toThrow(RateLimitException::class);
});
