<?php

use Iserter\UniformedAI\Exceptions\{ProviderException, RateLimitException, ValidationException, AuthenticationException};
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatMessage};

it('instantiates custom exceptions with context', function() {
    $e = new ProviderException('fail', provider: 'openai', status: 500, raw: ['error'=>'x']);
    expect($e->provider)->toBe('openai');
    expect($e->status)->toBe(500);
    $rle = new RateLimitException('rl', provider: 'openai', status: 429);
    expect($rle->getCode())->toBe(429);
    $ve = new ValidationException('bad', provider: 'openai');
    $ae = new AuthenticationException('auth', provider: 'openai');
    expect([$ve, $ae])->toHaveCount(2);
});

it('chat message rejects invalid role', function() {
    expect(fn() => new ChatMessage('bogus','hi'))->toThrow(ValidationException::class);
});

it('chat message rejects empty content for user role', function() {
    expect(fn() => new ChatMessage('user',''))->toThrow(ValidationException::class);
});

it('chat request validates messages array', function() {
    $msg = new ChatMessage('user','Hello');
    $r = new ChatRequest([$msg], model: 'gpt-test', temperature: 0.5, maxTokens: 10);
    expect($r->model)->toBe('gpt-test');
});

it('chat request rejects invalid temperature', function() {
    $msg = new ChatMessage('user','Hello');
    expect(fn() => new ChatRequest([$msg], temperature: 5.0))->toThrow(ValidationException::class);
});

it('chat request rejects negative maxTokens', function() {
    $msg = new ChatMessage('user','Hello');
    expect(fn() => new ChatRequest([$msg], maxTokens: 0))->toThrow(ValidationException::class);
});
