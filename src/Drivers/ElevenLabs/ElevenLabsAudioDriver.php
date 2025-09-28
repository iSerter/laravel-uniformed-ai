<?php

namespace Iserter\UniformedAI\Drivers\ElevenLabs;

use Iserter\UniformedAI\Contracts\Audio\AudioContract;
use Iserter\UniformedAI\DTOs\{AudioRequest, AudioResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class ElevenLabsAudioDriver implements AudioContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $this->limiter?->throttle('elevenlabs', (int) config('uniformed-ai.rate_limit.elevenlabs'));
        $http = HttpClientFactory::make($this->cfg)
            ->withHeaders(['xi-api-key' => $this->cfg['api_key']]);

        $voice = $request->voice ?? ($this->cfg['voice_id'] ?? 'Rachel');
        $res = $http->post("v1/text-to-speech/{$voice}", [
            'text' => $request->text,
            'model_id' => $this->cfg['model'] ?? 'eleven_multilingual_v2',
            'voice_settings' => $request->options['voice_settings'] ?? null,
            'output_format' => $request->format,
        ]);

        if (!$res->successful()) throw new ProviderException('ElevenLabs error', 'elevenlabs', $res->status(), $res->json());

        $b64 = base64_encode($res->body());
        return new AudioResponse($b64, ['headers' => $res->headers()]);
    }
}
