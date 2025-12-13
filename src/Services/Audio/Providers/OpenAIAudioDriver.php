<?php

namespace Iserter\UniformedAI\Services\Audio\Providers;

use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse, AudioTranscriptionRequest, AudioTranscriptionResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

/**
 * OpenAI Audio Driver for TTS (text-to-speech) and STT (speech-to-text/Whisper)
 * 
 * TTS API: POST /v1/audio/speech
 * STT API: POST /v1/audio/transcriptions (Whisper)
 */
class OpenAIAudioDriver implements AudioContract
{
    public function __construct(private array $cfg) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'openai');

        $voice = $request->voice ?? ($this->cfg['voice'] ?? 'alloy');
        $model = $request->model ?? ($this->cfg['tts_model'] ?? 'tts-1');
        
        // OpenAI TTS supports: mp3, opus, aac, flac, wav, pcm
        $format = $request->format ?: 'mp3';

        $payload = [
            'model' => $model,
            'input' => $request->text,
            'voice' => $voice,
            'response_format' => $format,
        ];

        // Merge additional options if provided (e.g., speed)
        if (!empty($request->options)) {
            $payload = array_merge($payload, $request->options);
        }

        $res = $http->post('v1/audio/speech', $payload);

        if (!$res->successful()) {
            $errorPayload = $res->json() ?? ['body' => $res->body()];
            throw new ProviderException(
                message: 'OpenAI text-to-speech failed',
                provider: 'openai',
                status: $res->status(),
                raw: $errorPayload,
            );
        }

        // Response is raw audio binary
        $b64 = base64_encode($res->body());
        
        return new AudioResponse($b64, [
            'model' => $model,
            'voice' => $voice,
            'format' => $format,
        ]);
    }

    public function transcribe(AudioTranscriptionRequest $request): AudioTranscriptionResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'openai');

        $model = $request->model ?? ($this->cfg['whisper_model'] ?? 'whisper-1');

        // Prepare multipart/form-data request
        $multipart = [
            [
                'name' => 'model',
                'contents' => $model,
            ],
            [
                'name' => 'file',
                'contents' => $request->isBase64 
                    ? base64_decode($request->audioFile) 
                    : fopen($request->audioFile, 'r'),
                'filename' => 'audio.mp3', // OpenAI requires a filename
            ],
        ];

        if ($request->language) {
            $multipart[] = [
                'name' => 'language',
                'contents' => $request->language,
            ];
        }

        // Request verbose JSON for more metadata
        $multipart[] = [
            'name' => 'response_format',
            'contents' => 'verbose_json',
        ];

        // Add timestamp granularities for detailed output
        $multipart[] = [
            'name' => 'timestamp_granularities[]',
            'contents' => 'segment',
        ];

        // Merge additional options
        if (!empty($request->options)) {
            foreach ($request->options as $key => $value) {
                if (!in_array($key, ['model', 'file', 'language', 'response_format'])) {
                    $multipart[] = [
                        'name' => $key,
                        'contents' => is_array($value) ? json_encode($value) : $value,
                    ];
                }
            }
        }

        $res = $http->asMultipart()->post('v1/audio/transcriptions', $multipart);

        if (!$res->successful()) {
            $errorPayload = $res->json() ?? ['body' => $res->body()];
            throw new ProviderException(
                message: 'OpenAI transcription failed',
                provider: 'openai',
                status: $res->status(),
                raw: $errorPayload,
            );
        }

        $json = $res->json();

        $text = $json['text'] ?? '';
        if (empty($text)) {
            throw new ProviderException(
                message: 'OpenAI transcription text missing',
                provider: 'openai',
                status: $res->status(),
                raw: $json,
            );
        }

        return new AudioTranscriptionResponse(
            text: $text,
            language: $json['language'] ?? $request->language,
            duration: $json['duration'] ?? null,
            raw: $json,
        );
    }

    public function getAvailableVoices(bool $refresh = false): array
    {
        // OpenAI TTS voices are: alloy, echo, fable, onyx, nova, shimmer
        // These are static and documented in the API
        return [
            'map' => [
                'alloy' => 'Alloy',
                'echo' => 'Echo',
                'fable' => 'Fable',
                'onyx' => 'Onyx',
                'nova' => 'Nova',
                'shimmer' => 'Shimmer',
            ],
            '_raw' => [
                'note' => 'OpenAI TTS static voice list',
                'voices' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
            ],
        ];
    }
}
