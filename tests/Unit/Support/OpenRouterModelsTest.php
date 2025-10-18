<?php

use Iserter\UniformedAI\Support\OpenRouterModels;
use Illuminate\Support\Facades\Http;

it('retrieves available models', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                ['id' => 'openai/gpt-4o-mini:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
            ],
        ], 200),
    ]);

    $orm = new OpenRouterModels('test-key');
    expect($orm->getAvailableModels())->toHaveCount(1);
});

it('caches and filters free models', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                ['id' => 'openai/gpt-4o-mini:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
                ['id' => 'meta-llama/llama-3.1-8b-instruct:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
                ['id' => 'anthropic/claude-3.5-sonnet', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
            ],
        ], 200),
    ]);

    $orm = new OpenRouterModels('test-key');
    $freeFirst = $orm->getFreeModels(fresh: true);
    expect($freeFirst)->toHaveCount(2);

    // Second call should use cache - simulate by changing fake response and ensuring count stays 2
    Http::fake([
        'https://openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
    ]);

    $cached = $orm->getFreeModels();
    expect($cached)->toHaveCount(2);
});

it('selects best free model preferring size and maker', function () {
    Http::fake([
        'https://openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                ['id' => 'openai/gpt-4o-mini:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
                ['id' => 'meta-llama/llama-3.3-70b-instruct:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
                ['id' => 'mistralai/mixtral-8x22b-instruct:free', 'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']]],
            ],
        ], 200),
    ]);

    $orm = new OpenRouterModels('test-key');
    $best = $orm->getBestFreeModel();
    expect($best['id'])->toBe('meta-llama/llama-3.3-70b-instruct:free');

    $smallest = $orm->getBestFreeModel(getSmallest: true);
    // With getSmallest=true we extend exact preferences; since an exact llama match exists it returns same llama model.
    expect($smallest['id'])->toBe('meta-llama/llama-3.3-70b-instruct:free');
});
