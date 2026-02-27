<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatMessage, ChatRequest};
use Iserter\UniformedAI\Services\Chat\Providers\OpenAIChatDriver;

it('ChatRequest accepts options array', function () {
    $req = new ChatRequest(
        messages: [new ChatMessage('user', 'Hi')],
        options: ['response_format' => ['type' => 'json_object'], 'seed' => 42],
    );

    expect($req->options)->toBe(['response_format' => ['type' => 'json_object'], 'seed' => 42]);
});

it('ChatRequest defaults to empty options', function () {
    $req = new ChatRequest(messages: [new ChatMessage('user', 'Hi')]);

    expect($req->options)->toBe([]);
});

it('OpenAI driver merges options into payload', function () {
    config()->set('uniformed-ai.providers.openai', [
        'api_key' => 'sk-test',
        'base_url' => 'https://api.openai.com/v1',
        'chat' => ['model' => 'gpt-4.1-mini'],
    ]);

    Http::fake(['api.openai.com/*' => Http::response([
        'choices' => [['message' => ['content' => 'Hi']]],
    ], 200)]);

    $driver = new OpenAIChatDriver(config('uniformed-ai.providers.openai'));
    $req = new ChatRequest(
        messages: [new ChatMessage('user', 'Hello')],
        options: ['response_format' => ['type' => 'json_object'], 'seed' => 42],
    );

    $driver->send($req);

    Http::assertSent(function ($request) {
        $body = json_decode($request->body(), true);
        return ($body['response_format']['type'] ?? null) === 'json_object'
            && ($body['seed'] ?? null) === 42;
    });
});
