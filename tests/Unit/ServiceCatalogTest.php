<?php

use Iserter\UniformedAI\Support\ServiceCatalog;
use Iserter\UniformedAI\Facades\AI;

it('exposes expected top level services', function () {
    $catalog = AI::catalog();
    expect($catalog)->toHaveKeys(['chat','image','audio','music','search']);
});

it('returns chat providers including openai', function () {
    $providers = AI::chat()->getProviders();
    expect($providers)->toContain('openai');
});

it('returns models for openai chat', function () {
    $models = AI::chat()->getModels('openai');
    expect($models)->toContain('gpt-4.1-mini');
});

it('returns empty array for unknown provider', function () {
    $models = AI::chat()->getModels('does-not-exist');
    expect($models)->toBeArray()->toBeEmpty();
});

it('has eleven labs audio model', function () {
    $models = AI::audio()->getModels('elevenlabs');
    expect($models)->toContain('eleven_multilingual_v2');
});

it('has tavily search models', function () {
    $models = AI::search()->getModels('tavily');
    expect($models)->toContain('tavily/advanced');
});
