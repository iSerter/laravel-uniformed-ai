<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Image\DTOs\ImageRequest;
use Iserter\UniformedAI\Services\Image\ImageManager;

it('generates image via OpenAI image driver', function() {
    config()->set('uniformed-ai.defaults.image', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [ ['b64_json' => base64_encode('fakepngdata')] ]
        ], 200)
    ]);

    $manager = app(ImageManager::class);
    $resp = $manager->create(new ImageRequest(prompt: 'A fox'));

    expect($resp->images)->toHaveCount(1);
    expect($resp->images[0]['b64'])->toBeString();
});
