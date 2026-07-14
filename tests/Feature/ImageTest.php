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

it('does not send response_format for gpt-image models', function() {
    config()->set('uniformed-ai.defaults.image', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');
    config()->set('uniformed-ai.providers.openai.image.model', 'gpt-image-1');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [ ['b64_json' => base64_encode('fakepngdata')] ]
        ], 200)
    ]);

    $manager = app(ImageManager::class);
    $manager->create(new ImageRequest(prompt: 'A fox'));

    Http::assertSent(fn ($request) => ! $request->hasHeader('response_format') && ! array_key_exists('response_format', $request->data()));
});

it('sends response_format for dall-e models', function() {
    config()->set('uniformed-ai.defaults.image', 'openai');
    config()->set('uniformed-ai.providers.openai.api_key', 'test');
    config()->set('uniformed-ai.providers.openai.image.model', 'dall-e-3');

    Http::fake([
        'api.openai.com/*' => Http::response([
            'data' => [ ['b64_json' => base64_encode('fakepngdata')] ]
        ], 200)
    ]);

    $manager = app(ImageManager::class);
    $manager->create(new ImageRequest(prompt: 'A fox'));

    Http::assertSent(fn ($request) => ($request->data()['response_format'] ?? null) === 'b64_json');
});
