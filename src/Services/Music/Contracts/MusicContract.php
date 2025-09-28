<?php

namespace Iserter\UniformedAI\Services\Music\Contracts;

use Iserter\UniformedAI\Services\Music\DTOs\MusicRequest;
use Iserter\UniformedAI\Services\Music\DTOs\MusicResponse;

interface MusicContract
{
    public function compose(MusicRequest $request): MusicResponse;
}
