<?php

namespace Iserter\UniformedAI\Services\Audio\Providers;

use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\CacheRepository;

class ElevenLabsAudioDriver implements AudioContract
{
    public function __construct(private array $cfg) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'elevenlabs')
            ->withHeaders(['xi-api-key' => $this->cfg['api_key']]);

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
     * Fetch available voices from ElevenLabs.
     * API reference: GET /v1/voices
     * Returns array keyed by voice_id => name (and includes raw data under _raw if caller wants details)
     */
    public function getAvailableVoices(bool $refresh = false): array
    {
        $cacheKey = 'uniformed-ai:elevenlabs:voices';
        $ttl = (int) config('uniformed-ai.cache.ttl', 3600);

        $loader = function() {
            $http = HttpClientFactory::make($this->cfg, 'elevenlabs')
                ->withHeaders(['xi-api-key' => $this->cfg['api_key']]);
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
}
