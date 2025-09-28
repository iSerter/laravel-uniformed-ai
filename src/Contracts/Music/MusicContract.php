<?php

namespace Iserter\UniformedAI\Contracts\Music;

use Iserter\UniformedAI\DTOs\{MusicRequest, MusicResponse};

interface MusicContract
{
    public function compose(MusicRequest $request): MusicResponse;
}
