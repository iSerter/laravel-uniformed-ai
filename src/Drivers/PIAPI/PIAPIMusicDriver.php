<?php

namespace Iserter\UniformedAI\Drivers\PIAPI;

use Iserter\UniformedAI\Contracts\Music\MusicContract;
use Iserter\UniformedAI\DTOs\{MusicRequest, MusicResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class PIAPIMusicDriver implements MusicContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function compose(MusicRequest $request): MusicResponse
    {
        $this->limiter?->throttle('piapi', (int) config('uniformed-ai.rate_limit.piapi'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'text' => $request->text,
            'style' => $request->style,
            'format' => $request->format,
        ];
        $res = $http->post('music', $payload);
        if (!$res->successful()) throw new ProviderException('PIAPI music error', 'piapi', $res->status(), $res->json());
        $b64 = $res->json('audio_b64') ?? base64_encode($res->body());
        return new MusicResponse($b64, $res->json());
    }
}
