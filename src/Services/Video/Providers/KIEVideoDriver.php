<?php

namespace Iserter\UniformedAI\Services\Video\Providers;

use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;

/**
 * Placeholder KIE video driver.
 */
class KIEVideoDriver implements VideoContract
{
    public function __construct(private array $config) {}

    public function generate(VideoRequest $request): VideoResponse
    {
        throw new ProviderException('KIE video generation not implemented yet');
    }

    public function edit(VideoRequest $request): VideoResponse
    {
        throw new ProviderException('KIE video edit not implemented yet');
    }
}
