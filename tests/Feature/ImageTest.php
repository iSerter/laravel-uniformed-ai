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

it('generates image via Google image driver', function() {
    config()->set('uniformed-ai.defaults.image', 'google');
    config()->set('uniformed-ai.providers.google.api_key', 'test');
    config()->set('uniformed-ai.providers.google.image.model', 'gemini-3.1-flash-lite-image');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'output_image' => ['data' => base64_encode('fakepngdata')],
        ], 200),
    ]);

    $manager = app(ImageManager::class);
    $resp = $manager->create(new ImageRequest(prompt: 'A fox', size: '1024x1024'));

    expect($resp->images)->toHaveCount(1);
    expect($resp->images[0]['b64'])->toBeString();

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['model'] === 'gemini-3.1-flash-lite-image'
            && $data['input'][0]['type'] === 'text'
            && $data['response_format']['aspect_ratio'] === '1:1'
            && $request->hasHeader('x-goog-api-key', 'test');
    });
});

it('extracts images from the steps array when output_image is absent', function() {
    config()->set('uniformed-ai.defaults.image', 'google');
    config()->set('uniformed-ai.providers.google.api_key', 'test');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'steps' => [
                ['type' => 'model_output', 'content' => [
                    ['type' => 'text', 'text' => 'here you go'],
                    ['type' => 'image', 'data' => base64_encode('fakepngdata'), 'mime_type' => 'image/png'],
                ]],
            ],
        ], 200),
    ]);

    $manager = app(ImageManager::class);
    $resp = $manager->create(new ImageRequest(prompt: 'A fox'));

    expect($resp->images)->toHaveCount(1);
    expect($resp->images[0]['b64'])->toBeString();
});

it('sends the reference image as inline base64 data on modify()', function() {
    config()->set('uniformed-ai.defaults.image', 'google');
    config()->set('uniformed-ai.providers.google.api_key', 'test');

    $tempFile = tempnam(sys_get_temp_dir(), 'google_ref_').'.png';
    file_put_contents($tempFile, 'reference-bytes');

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'output_image' => ['data' => base64_encode('fakepngdata')],
        ], 200),
    ]);

    $manager = app(ImageManager::class);
    $manager->modify(new ImageRequest(prompt: 'Make it blue', imagePath: $tempFile));

    unlink($tempFile);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $data['input'][0]['type'] === 'image'
            && $data['input'][0]['data'] === base64_encode('reference-bytes')
            && $data['input'][1]['type'] === 'text';
    });
});
