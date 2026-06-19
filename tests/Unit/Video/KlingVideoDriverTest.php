<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Video\Providers\KlingVideoDriver;
use Iserter\UniformedAI\Services\Video\DTOs\VideoRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('generates a text-to-video and polls until succeed', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'test-kling-key',
        'base_url' => 'https://api.klingai.com',
        'video_model' => 'kling-v2-1',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/text2video' => Http::response([
            'data' => ['task_id' => 'task-abc-123'],
        ], 200),
        'api.klingai.com/v1/videos/text2video/*' => Http::response([
            'data' => [
                'task_status' => 'succeed',
                'task_result' => [
                    'videos' => [
                        ['url' => 'https://cdn.klingai.com/video.mp4'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'A cat playing piano', durationSeconds: 5, options: [
        'aspect_ratio' => '9:16',
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://cdn.klingai.com/video.mp4');
    expect($res->format)->toBe('mp4');
});

it('routes to image2video when image option is provided', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'test-kling-key',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/image2video' => Http::response([
            'data' => ['task_id' => 'task-img-456'],
        ], 200),
        'api.klingai.com/v1/videos/image2video/*' => Http::response([
            'data' => [
                'task_status' => 'succeed',
                'task_result' => [
                    'videos' => [
                        ['url' => 'https://cdn.klingai.com/img2vid.mp4'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Animate this image', options: [
        'image_url' => 'https://example.com/photo.jpg',
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://cdn.klingai.com/img2vid.mp4');

    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'image2video')
            && ! str_contains($request->url(), 'text2video');
    });
});

it('uses JWT auth when access_key and secret_key are provided', function () {
    config()->set('uniformed-ai.providers.kling', [
        'access_key' => 'test-access-key',
        'secret_key' => 'test-secret-key',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/text2video' => Http::response([
            'data' => ['task_id' => 'task-jwt-789'],
        ], 200),
        'api.klingai.com/v1/videos/text2video/*' => Http::response([
            'data' => [
                'task_status' => 'succeed',
                'task_result' => [
                    'videos' => [
                        ['url' => 'https://cdn.klingai.com/jwt.mp4'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'JWT test', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://cdn.klingai.com/jwt.mp4');

    Http::assertSent(function ($request) {
        $authHeader = $request->header('Authorization')[0] ?? '';
        // JWT should have 3 dot-separated parts
        return str_starts_with($authHeader, 'Bearer ')
            && count(explode('.', str_replace('Bearer ', '', $authHeader))) === 3;
    });
});

it('uses plain Bearer when only api_key is provided', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'my-gateway-token',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/text2video' => Http::response([
            'data' => ['task_id' => 'task-bearer-111'],
        ], 200),
        'api.klingai.com/v1/videos/text2video/*' => Http::response([
            'data' => [
                'task_status' => 'succeed',
                'task_result' => [
                    'videos' => [
                        ['url' => 'https://cdn.klingai.com/bearer.mp4'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Bearer test', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    Http::assertSent(function ($request) {
        $authHeader = $request->header('Authorization')[0] ?? '';
        return $authHeader === 'Bearer my-gateway-token';
    });
});

it('throws on create failure', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/*' => Http::response(['error' => 'Bad Request'], 400),
    ]);

    $req = VideoRequest::make(prompt: 'Fail test');

    $driver->generate($req);
})->throws(ProviderException::class, 'Kling video generate failed');

it('throws on poll failure status', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/text2video' => Http::response([
            'data' => ['task_id' => 'task-fail-222'],
        ], 200),
        'api.klingai.com/v1/videos/text2video/*' => Http::response([
            'data' => [
                'task_status' => 'failed',
                'task_status_msg' => 'Content moderation failed',
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Poll fail', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'Kling video generation failed');

it('throws on poll timeout', function () {
    config()->set('uniformed-ai.providers.kling', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.klingai.com',
    ]);

    $driver = new KlingVideoDriver(config('uniformed-ai.providers.kling'));

    Http::fake([
        'api.klingai.com/v1/videos/text2video' => Http::response([
            'data' => ['task_id' => 'task-timeout-333'],
        ], 200),
        'api.klingai.com/v1/videos/text2video/*' => Http::response([
            'data' => ['task_status' => 'processing'],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Timeout test', options: [
        'poll' => ['interval' => 0, 'timeout' => 0],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'Kling video generation timeout');
