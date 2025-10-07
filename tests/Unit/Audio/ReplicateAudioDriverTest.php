<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Audio\Providers\ReplicateAudioDriver;
use Iserter\UniformedAI\Services\Audio\DTOs\AudioRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;

it('creates a replicate prediction and downloads audio', function(){
    config()->set('uniformed-ai.providers.replicate', [
        'api_key' => 'replicate-key',
        'base_url' => 'https://api.replicate.com/v1',
        'model' => 'some-owner/some-model:abcdef123',
    ]);

    $driver = new ReplicateAudioDriver(config('uniformed-ai.providers.replicate'));

    $predictionPayload = [
        'id' => 'pred_123',
        'status' => 'succeeded',
        'output' => ['https://replicate.delivery/pb/files/audio-file.mp3'],
        'metrics' => ['predict_time' => 1.23],
    ];

    $audioBinary = random_bytes(64);

    Http::fake([
        // prediction create
        'api.replicate.com/v1/predictions' => Http::response($predictionPayload, 200),
        // asset fetch
        'replicate.delivery/*' => Http::response($audioBinary, 200),
    ]);

    $req = new AudioRequest(text: 'Hello replicate', voice: 'alice');
    $resp = $driver->speak($req);

    expect(base64_decode($resp->b64Audio))->toBe($audioBinary);
    expect($resp->raw['prediction_id'])->toBe('pred_123');
});

it('throws when prediction fails', function(){
    config()->set('uniformed-ai.providers.replicate', [
        'api_key' => 'replicate-key',
        'base_url' => 'https://api.replicate.com/v1',
        'model' => 'some-owner/some-model:abcdef123',
    ]);

    $driver = new ReplicateAudioDriver(config('uniformed-ai.providers.replicate'));

    Http::fake([
        'api.replicate.com/v1/predictions' => Http::response(['id' => 'pred_bad', 'status' => 'processing'], 200),
    ]);

    $this->expectException(ProviderException::class);
    $driver->speak(new AudioRequest(text: 'Hi'));
});
