<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Audio\Providers\OpenAIAudioDriver;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioTranscriptionRequest};
use Iserter\UniformedAI\Exceptions\ProviderException;

beforeEach(function() {
    config()->set('uniformed-ai.providers.openai', [
        'api_key' => 'test-openai-key',
        'base_url' => 'https://api.openai.com',
        'tts_model' => 'tts-1',
        'whisper_model' => 'whisper-1',
        'voice' => 'alloy',
    ]);
});

it('speaks text using OpenAI TTS API', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    $rawAudio = random_bytes(64);
    
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response($rawAudio, 200),
    ]);
    
    $req = new AudioRequest(
        text: 'Hello from OpenAI TTS',
        voice: 'nova',
        format: 'mp3',
        model: 'tts-1-hd',
    );
    
    $res = $driver->speak($req);
    
    expect(base64_decode($res->b64Audio))->toBe($rawAudio);
    expect($res->raw['voice'])->toBe('nova');
    expect($res->raw['model'])->toBe('tts-1-hd');
    expect($res->raw['format'])->toBe('mp3');
});

it('uses default voice and model if not provided', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    $rawAudio = random_bytes(32);
    
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response($rawAudio, 200),
    ]);
    
    $req = new AudioRequest(text: 'Test default config');
    
    $res = $driver->speak($req);
    
    expect($res->raw['voice'])->toBe('alloy'); // from config default
    expect($res->raw['model'])->toBe('tts-1'); // from config default
});

it('transcribes audio using OpenAI Whisper API', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    $transcriptJson = [
        'text' => 'This is a test transcription from Whisper.',
        'language' => 'en',
        'duration' => 2.5,
        'segments' => [
            [
                'id' => 0,
                'start' => 0.0,
                'end' => 2.5,
                'text' => 'This is a test transcription from Whisper.',
            ]
        ],
    ];
    
    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response($transcriptJson, 200),
    ]);
    
    // Create a temporary test audio file
    $tempFile = tempnam(sys_get_temp_dir(), 'audio_test_');
    file_put_contents($tempFile, random_bytes(100));
    
    $req = new AudioTranscriptionRequest(
        audioFile: $tempFile,
        language: 'en',
        model: 'whisper-1',
    );
    
    $res = $driver->transcribe($req);
    
    expect($res->text)->toBe('This is a test transcription from Whisper.');
    expect($res->language)->toBe('en');
    expect($res->duration)->toBe(2.5);
    expect($res->raw['segments'])->toBeArray();
    
    // Cleanup
    unlink($tempFile);
});

it('transcribes base64 encoded audio', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    $transcriptJson = [
        'text' => 'Base64 transcription test.',
        'language' => 'es',
        'duration' => 1.2,
    ];
    
    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response($transcriptJson, 200),
    ]);
    
    $audioContent = random_bytes(50);
    $base64Audio = base64_encode($audioContent);
    
    $req = new AudioTranscriptionRequest(
        audioFile: $base64Audio,
        isBase64: true,
        language: 'es',
    );
    
    $res = $driver->transcribe($req);
    
    expect($res->text)->toBe('Base64 transcription test.');
    expect($res->language)->toBe('es');
});

it('throws exception when TTS API fails', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
    ]);
    
    $req = new AudioRequest(text: 'This will fail');
    
    $driver->speak($req);
})->throws(ProviderException::class, 'OpenAI text-to-speech failed');

it('throws exception when transcription API fails', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    Http::fake([
        'api.openai.com/v1/audio/transcriptions' => Http::response(['error' => ['message' => 'Invalid file format']], 400),
    ]);
    
    $tempFile = tempnam(sys_get_temp_dir(), 'audio_test_');
    file_put_contents($tempFile, 'invalid audio data');
    
    $req = new AudioTranscriptionRequest(audioFile: $tempFile);
    
    try {
        $driver->transcribe($req);
    } finally {
        unlink($tempFile);
    }
})->throws(ProviderException::class, 'OpenAI transcription failed');

it('returns static voice list', function() {
    $driver = new OpenAIAudioDriver(config('uniformed-ai.providers.openai'));
    
    $voices = $driver->getAvailableVoices();
    
    expect($voices['map'])->toBeArray();
    expect($voices['map'])->toHaveKeys(['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer']);
    expect($voices['map']['alloy'])->toBe('Alloy');
    expect($voices['_raw']['note'])->toContain('static');
});
