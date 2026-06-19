<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Video\Providers\ElevenLabsVideoDriver;
use Iserter\UniformedAI\Services\Video\DTOs\VideoRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('generates a video and polls until completed', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-elevenlabs-key',
        'base_url' => 'https://api.elevenlabs.io',
        'video_model' => 'google-veo-3-fast',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response([
            'id' => 'gen-abc-123',
        ], 200),
        'api.elevenlabs.io/v1/image-video/generations/*' => Http::response([
            'status' => 'completed',
            'outputs' => [
                ['url' => 'https://cdn.elevenlabs.io/video-output.mp4'],
            ],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'A beautiful sunset', durationSeconds: 4, options: [
        'aspect_ratio' => '16:9',
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $res = $driver->generate($req);

    expect($res->url)->toBe('https://cdn.elevenlabs.io/video-output.mp4');
    expect($res->format)->toBe('mp4');
    expect($res->hasUrl())->toBeTrue();
});

it('passes all parameters correctly', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.elevenlabs.io',
        'video_model' => 'google-veo-3-fast',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response(['id' => 'gen-params'], 200),
        'api.elevenlabs.io/v1/image-video/generations/*' => Http::response([
            'status' => 'completed',
            'outputs' => [['url' => 'https://cdn.elevenlabs.io/params.mp4']],
        ], 200),
    ]);

    $req = VideoRequest::make(
        prompt: 'Param test',
        durationSeconds: 6,
        model: 'wan-ai',
        options: [
            'aspect_ratio' => '9:16',
            'resolution' => '4k',
            'negative_prompt' => 'blurry',
            'enable_audio' => true,
            'start_frame_url' => 'https://example.com/start.jpg',
            'end_frame_url' => 'https://example.com/end.jpg',
            'poll' => ['interval' => 0, 'timeout' => 10],
        ],
    );

    $res = $driver->generate($req);
    expect($res->url)->toBe('https://cdn.elevenlabs.io/params.mp4');

    Http::assertSent(function ($request) {
        if (str_contains($request->url(), 'generations') && $request->method() === 'POST') {
            $body = $request->data();
            return ($body['model_id'] ?? null) === 'wan-ai'
                && ($body['duration_seconds'] ?? null) === 6
                && ($body['aspect_ratio'] ?? null) === '9:16'
                && ($body['resolution'] ?? null) === '4k'
                && ($body['negative_prompt'] ?? null) === 'blurry'
                && ($body['enable_audio'] ?? null) === true
                && ($body['start_frame_url'] ?? null) === 'https://example.com/start.jpg'
                && ($body['end_frame_url'] ?? null) === 'https://example.com/end.jpg';
        }
        return true;
    });
});

it('throws when generation id is missing', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.elevenlabs.io',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response([
            'status' => 'queued',
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'No ID test');

    $driver->generate($req);
})->throws(ProviderException::class, 'ElevenLabs video generate missing generation id');

it('throws on create failure', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.elevenlabs.io',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/*' => Http::response(['error' => 'Unauthorized'], 401),
    ]);

    $req = VideoRequest::make(prompt: 'Auth fail');

    $driver->generate($req);
})->throws(ProviderException::class, 'ElevenLabs video generate failed');

it('throws on poll failure status', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.elevenlabs.io',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response(['id' => 'gen-fail'], 200),
        'api.elevenlabs.io/v1/image-video/generations/*' => Http::response([
            'status' => 'failed',
            'error' => 'Generation failed due to content policy',
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Fail poll', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'ElevenLabs video generation failed');

it('throws on poll timeout', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'test-key',
        'base_url' => 'https://api.elevenlabs.io',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response(['id' => 'gen-timeout'], 200),
        'api.elevenlabs.io/v1/image-video/generations/*' => Http::response([
            'status' => 'processing',
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Timeout test', options: [
        'poll' => ['interval' => 0, 'timeout' => 0],
    ]);

    $driver->generate($req);
})->throws(ProviderException::class, 'ElevenLabs video generation timeout');

it('uses xi-api-key header for authentication', function () {
    config()->set('uniformed-ai.providers.elevenlabs', [
        'api_key' => 'my-xi-api-key-12345',
        'base_url' => 'https://api.elevenlabs.io',
    ]);

    $driver = new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs'));

    Http::fake([
        'api.elevenlabs.io/v1/image-video/generations' => Http::response(['id' => 'gen-auth'], 200),
        'api.elevenlabs.io/v1/image-video/generations/*' => Http::response([
            'status' => 'completed',
            'outputs' => [['url' => 'https://cdn.elevenlabs.io/auth.mp4']],
        ], 200),
    ]);

    $req = VideoRequest::make(prompt: 'Auth header test', options: [
        'poll' => ['interval' => 0, 'timeout' => 10],
    ]);

    $driver->generate($req);

    Http::assertSent(function ($request) {
        $xiKey = $request->header('xi-api-key')[0] ?? '';
        return $xiKey === 'my-xi-api-key-12345';
    });
});
