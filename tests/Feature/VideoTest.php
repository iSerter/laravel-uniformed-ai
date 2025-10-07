<?php

use Iserter\UniformedAI\Facades\AI;
use Iserter\UniformedAI\Services\Video\DTOs\VideoRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('can resolve video manager and list providers', function() {
    $mgr = AI::video();
    expect($mgr)->not()->toBeNull();
    $providers = $mgr->getProviders();
    expect($providers)->toContain('replicate');
    expect($providers)->toContain('kie');
});

it('throws for unimplemented replicate driver', function() {
    config()->set('uniformed-ai.defaults.video', 'replicate');
    $this->expectException(ProviderException::class);
    AI::video()->generate(new VideoRequest(prompt: 'test video'));
});
