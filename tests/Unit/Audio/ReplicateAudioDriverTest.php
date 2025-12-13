<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Audio\Providers\ReplicateAudioDriver;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioTranscriptionRequest};
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

it('transcribes audio using Replicate Whisper model', function() {
    config()->set('uniformed-ai.providers.replicate', [
        'api_key' => 'replicate-key',
        'base_url' => 'https://api.replicate.com/v1',
        'transcription_model' => 'openai/whisper:test123',
    ]);
    
    $driver = new ReplicateAudioDriver(config('uniformed-ai.providers.replicate'));
    
    $predictionPayload = [
        'id' => 'pred_transcribe_123',
        'status' => 'succeeded',
        'output' => [
            'transcription' => 'This is a Replicate Whisper transcription.',
            'detected_language' => 'en',
        ],
        'metrics' => ['predict_time' => 2.5],
    ];
    
    Http::fake([
        'api.replicate.com/v1/predictions' => Http::response($predictionPayload, 200),
    ]);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'audio_test_');
    file_put_contents($tempFile, random_bytes(100));
    
    $req = new AudioTranscriptionRequest(
        audioFile: $tempFile,
        language: 'en',
    );
    
    $res = $driver->transcribe($req);
    
    expect($res->text)->toBe('This is a Replicate Whisper transcription.');
    expect($res->language)->toBe('en');
    expect($res->raw['prediction_id'])->toBe('pred_transcribe_123');
    
    unlink($tempFile);
});

it('handles base64 audio for transcription', function() {
    config()->set('uniformed-ai.providers.replicate', [
        'api_key' => 'replicate-key',
        'base_url' => 'https://api.replicate.com/v1',
    ]);
    
    $driver = new ReplicateAudioDriver(config('uniformed-ai.providers.replicate'));
    
    $predictionPayload = [
        'id' => 'pred_b64_transcribe',
        'status' => 'succeeded',
        'output' => ['text' => 'Base64 audio transcription.'],
        'metrics' => [],
    ];
    
    Http::fake([
        'api.replicate.com/v1/predictions' => Http::response($predictionPayload, 200),
    ]);
    
    $audioContent = random_bytes(50);
    $base64Audio = base64_encode($audioContent);
    
    $req = new AudioTranscriptionRequest(
        audioFile: $base64Audio,
        isBase64: true,
    );
    
    $res = $driver->transcribe($req);
    
    expect($res->text)->toBe('Base64 audio transcription.');
});
