<?php

namespace Iserter\UniformedAI\Services\Video\Contracts;

use Iserter\UniformedAI\Services\Video\DTOs\VideoRequest;
use Iserter\UniformedAI\Services\Video\DTOs\VideoResponse;

interface VideoContract
{
    /** Generate a video from the given request */
    public function generate(VideoRequest $request): VideoResponse;

    /** Optional: future editing / variation endpoint */
    public function edit(VideoRequest $request): VideoResponse;
}
