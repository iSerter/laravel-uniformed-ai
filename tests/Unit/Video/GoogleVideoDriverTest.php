<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Video\Providers\GoogleVideoDriver;
use Iserter\UniformedAI\Services\Video\DTOs\VideoRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('generates a video via text-to-video and polls until done', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
        'video_model' => 'veo-3.1-generate-preview',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'name' => 'operations/generate-video-12345',
        ], 200),
        'generativelanguage.googleapis.com/v1beta/operations/*' => Http::response([
            'done' => true,
            'response' => [
                'generateVideoResponse' => [
                    'generatedSamples' => [
                        ['video' => ['uri' => 'https://storage.googleapis.com/video.mp4']],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'A sunset over the ocean', durationSeconds: 5, options: [
        'aspect_ratio' => '16:9',
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://storage.googleapis.com/video.mp4');
    expect($res->format)->toBe('mp4');
    expect($res->hasUrl())->toBeTrue();
});

it('throws on 500 error from Google Veo', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => 'Internal Server Error'], 500),
    ]);

    $req = VideoRequest::make(prompt: 'A sunset');

    $driver->generate($req);
})->throws(ProviderException::class, 'Google Veo generate failed');

it('throws when operation name is missing', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'status' => 'queued',
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'A sunset');

    $driver->generate($req);
})->throws(ProviderException::class, 'Google Veo generate missing operation name');

it('throws on poll timeout', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'name' => 'operations/timeout-test',
        ], 200),
        'generativelanguage.googleapis.com/v1beta/operations/*' => Http::response([
            'done' => false,
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'A sunset', options: [
        'poll' => ['interval' => 0, 'timeout' => 0],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'Google Veo generation timeout');

it('throws on poll error response', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'name' => 'operations/error-test',
        ], 200),
        'generativelanguage.googleapis.com/v1beta/operations/*' => Http::response([
            'done' => true,
            'error' => ['message' => 'Content policy violation', 'code' => 400],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Something bad', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'Google Veo operation failed');

it('throws when no video URI in response', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'name' => 'operations/no-uri-test',
        ], 200),
        'generativelanguage.googleapis.com/v1beta/operations/*' => Http::response([
            'done' => true,
            'response' => [
                'generateVideoResponse' => [
                    'generatedSamples' => [],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Empty result', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'Google Veo generation returned no video URI');

it('passes parameters correctly including image-to-video', function () {
    config()->set('uniformed-ai.providers.google', [
        'api_key' => 'test-google-key',
        'base_url' => 'https://generativelanguage.googleapis.com',
        'video_model' => 'veo-3.1-generate-preview',
    ]);

    $driver = new GoogleVideoDriver(config('uniformed-ai.providers.google'));

    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*' => Http::response([
            'name' => 'operations/params-test',
        ], 200),
        'generativelanguage.googleapis.com/v1beta/operations/*' => Http::response([
            'done' => true,
            'response' => [
                'generateVideoResponse' => [
                    'generatedSamples' => [
                        ['video' => ['uri' => 'https://storage.googleapis.com/params.mp4']],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(
        prompt: 'Image to video test',
        durationSeconds: 8,
        options: [
            'aspect_ratio' => '9:16',
            'resolution' => '720p',
            'negative_prompt' => 'blurry, distorted',
            'image_inline_data' => [
                'data' => base64_encode('fake-image-data'),
                'mimeType' => 'image/png',
            ],
            'poll' => ['interval' => 0, 'timeout' => 10],
        ],
    );

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://storage.googleapis.com/params.mp4');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'predictLongRunning')) {
            $body = $request->data();
            return isset($body['instances'][0]['image'])
                && isset($body['parameters']['aspectRatio'])
                && $body['parameters']['aspectRatio'] === '9:16'
                && $body['parameters']['negativePrompt'] === 'blurry, distorted';
        }
        return true;
    });
});
