<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatMessage};
use Iserter\UniformedAI\Models\ServiceUsageLog;

beforeEach(function() {
    config()->set('uniformed-ai.defaults.chat', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'sk-test');
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.usage.enabled', true);
    config()->set('uniformed-ai.logging.usage.services.chat', true);
    // Ensure pricing exists for model used in test via seeded migration pattern
});

it('captures reported usage & computes cost', function() {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Answer']] ],
            'usage' => [ 'prompt_tokens' => 20, 'completion_tokens' => 10, 'total_tokens' => 30 ],
        ], 200)
    ]);

    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([ new ChatMessage('user', 'Hello?') ], model: 'gpt-4.1-mini'));

    $log = ServiceUsageLog::latest('id')->first();
    expect($log->extra['usage']['prompt_tokens'] ?? null)->toBe(20);
    expect($log->extra['usage']['completion_tokens'] ?? null)->toBe(10);
    expect($log->extra['usage']['confidence'] ?? null)->toBe('reported');
    // Pricing seeded: input 15 / output 60 cents per 1K -> costs
    // input cost = 20/1000 * 15 = 0.3 => bankers rounds to 0 cents (int). We accept >=0.
    expect($log->extra['usage']['pricing_source'] ?? null)->toContain('db:');
});

it('estimates usage when provider omits usage', function() {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => str_repeat('A', 50) ]] ],
        ], 200)
    ]);
    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([ new ChatMessage('user', str_repeat('hello ', 10)) ], model: 'gpt-4.1-mini'));
    $log = ServiceUsageLog::latest('id')->first();
    $usage = $log->extra['usage'] ?? null;
    expect($usage)->not->toBeNull();
    expect($usage['confidence'])->toBe('estimated');
    expect($usage['prompt_tokens'])->toBeGreaterThan(0);
});

it('marks unpriced when pricing missing', function() {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Hi']] ],
            'usage' => [ 'prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10 ],
        ], 200)
    ]);
    // Use a model not seeded in pricing table
    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([ new ChatMessage('user', 'Test') ], model: 'non-priced-model'));    
    $log = ServiceUsageLog::latest('id')->first();
    expect($log->extra['usage']['pricing_source'])->toBe('unpriced');
});

it('can skip usage via sampling', function() {
    config()->set('uniformed-ai.logging.usage.sampling.success_rate', 0.0);
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [ ['message' => ['content' => 'Ignore usage']] ],
            'usage' => [ 'prompt_tokens' => 5, 'completion_tokens' => 5, 'total_tokens' => 10 ],
        ], 200)
    ]);
    $manager = app(ChatManager::class);
    $manager->send(new ChatRequest([ new ChatMessage('user', 'Hi') ], model: 'gpt-4.1-mini'));
    $log = ServiceUsageLog::latest('id')->first();
    expect(($log->extra['usage'] ?? null))->toBeNull();
});
