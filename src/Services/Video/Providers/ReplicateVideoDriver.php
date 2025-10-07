<?php

namespace Iserter\UniformedAI\Services\Video\Providers;

use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;

/**
 * Placeholder Replicate video driver.
 * Not implemented yet – throws informative exception.
 */
class ReplicateVideoDriver implements VideoContract
{
    public function __construct(private array $config) {}

    public function generate(VideoRequest $request): VideoResponse
    {
        throw new ProviderException('Replicate video generation not implemented yet');
    }

    public function edit(VideoRequest $request): VideoResponse
    {
        throw new ProviderException('Replicate video edit not implemented yet');
    }
}
