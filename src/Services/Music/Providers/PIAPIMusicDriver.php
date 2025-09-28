<?php

namespace Iserter\UniformedAI\Services\Music\Providers;

use Iserter\UniformedAI\Services\Music\Contracts\MusicContract;
use Iserter\UniformedAI\Services\Music\DTOs\{MusicRequest, MusicResponse};

class PIAPIMusicDriver implements MusicContract
{
    public function __construct(private array $cfg) {}

    public function compose(MusicRequest $request): MusicResponse
    {
        // Placeholder implementation
        return new MusicResponse(base64_encode('music-bytes'), ['placeholder' => true]);
    }
}
