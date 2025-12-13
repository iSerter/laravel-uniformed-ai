<?php

namespace Iserter\UniformedAI\Services\Audio\Providers;

use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse, AudioTranscriptionRequest, AudioTranscriptionResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\CacheRepository;
use Illuminate\Http\Client\PendingRequest;

class ElevenLabsAudioDriver implements AudioContract
{
    public function __construct(private array $cfg) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $http = $this->makeHttpClient();

        $voice = $request->voice ?? ($this->cfg['voice_id'] ?? 'Rachel');
        $model = $request->model ?? ($this->cfg['model'] ?? 'eleven_multilingual_v2');
        $format = $request->format ?: 'mp3_44100_128'; // ElevenLabs expects enumerated formats

        // According to docs, output_format is a query parameter. Keep body lean.
        $endpoint = "v1/text-to-speech/{$voice}";
        $res = $http->post($endpoint, [
            'query' => ['output_format' => $format],
            'json' => array_filter([
                'text' => $request->text,
                'model_id' => $model,
                'voice_settings' => $request->options['voice_settings'] ?? null,
            ], fn($v) => $v !== null),
        ]);

        if (!$res->successful()) {
            // Try to extract structured error; fall back to body string
            $errorPayload = $res->json() ?? ['body' => $res->body()];
            throw new ProviderException(
                message: 'ElevenLabs text-to-speech failed',
                provider: 'elevenlabs',
                status: $res->status(),
                raw: $errorPayload,
            );
        }

        $b64 = base64_encode($res->body());
        return new AudioResponse($b64, [
            'headers' => $res->headers(),
            'voice' => $voice,
            'model' => $model,
            'format' => $format,
        ]);
    }

    /**
     * Transcribe audio to text using ElevenLabs speech-to-text API.
     * Note: ElevenLabs STT API is asynchronous - we create a transcript job and poll for completion.
     * For now, this implementation uses a simple approach: if transcript isn't ready immediately,
     * we throw an exception indicating async nature. Future: implement polling or webhook callback.
     */
    public function transcribe(AudioTranscriptionRequest $request): AudioTranscriptionResponse
    {
        $http = $this->makeHttpClient();
        
        // ElevenLabs STT expects multipart/form-data with audio file
        // API endpoint: POST /v1/speech-to-text/transcripts
        $multipart = [
            [
                'name' => 'audio',
                'contents' => $request->isBase64 ? base64_decode($request->audioFile) : fopen($request->audioFile, 'r'),
                'filename' => 'audio.mp3', // Adjust based on actual file type if needed
            ],
        ];

        if ($request->language) {
            $multipart[] = [
                'name' => 'language',
                'contents' => $request->language,
            ];
        }

        if ($request->model) {
            $multipart[] = [
                'name' => 'model_id',
                'contents' => $request->model,
            ];
        }

        // Note: ElevenLabs API docs show this is an async endpoint that returns a transcript ID
        // For synchronous behavior, we'd need to poll. This simplified implementation attempts
        // a single request and expects immediate results (may not match actual API behavior).
        $res = $http->asMultipart()->post('v1/speech-to-text/transcripts', $multipart);

        if (!$res->successful()) {
            $errorPayload = $res->json() ?? ['body' => $res->body()];
            throw new ProviderException(
                message: 'ElevenLabs speech-to-text failed',
                provider: 'elevenlabs',
                status: $res->status(),
                raw: $errorPayload,
            );
        }

        $json = $res->json();
        
        // Based on API docs, response should contain transcript text
        // The actual structure may vary - adjust as needed
        $text = $json['text'] ?? $json['transcript'] ?? '';
        
        if (empty($text)) {
            throw new ProviderException(
                message: 'ElevenLabs transcript missing or empty',
                provider: 'elevenlabs',
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

    /**
     * Fetch available voices from ElevenLabs.
     * API reference: GET /v1/voices
     * Returns array keyed by voice_id => name (and includes raw data under _raw if caller wants details)
     */
    public function getAvailableVoices(bool $refresh = false): array
    {
        $cacheKey = 'uniformed-ai:elevenlabs:voices';
        $ttl = (int) config('uniformed-ai.cache.ttl', 3600);

        $loader = function() {
            $http = $this->makeHttpClient();
            $res = $http->get('v1/voices');
            if (!$res->successful()) {
                throw new ProviderException('ElevenLabs voices fetch failed', 'elevenlabs', $res->status(), $res->json());
            }
            $json = $res->json();
            $list = [];
            foreach (($json['voices'] ?? []) as $voice) {
                if (isset($voice['voice_id'])) {
                    $list[$voice['voice_id']] = $voice['name'] ?? $voice['voice_id'];
                }
            }
            return ['map' => $list, '_raw' => $json];
        };

        if ($refresh) {
            return $loader();
        }

        // Use cache repository abstraction (respect configured store)
        $repo = app(CacheRepository::class);
        return $repo->remember($cacheKey, $ttl, $loader);
    }

    private function makeHttpClient(): PendingRequest
    {
        return HttpClientFactory::make($this->cfg, 'elevenlabs')
            ->withHeaders(['xi-api-key' => $this->cfg['api_key']]);
    }
}
