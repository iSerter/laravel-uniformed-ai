<?php

namespace Iserter\UniformedAI\Services\Audio\Contracts;

use Iserter\UniformedAI\Services\Audio\DTOs\AudioRequest;
use Iserter\UniformedAI\Services\Audio\DTOs\AudioResponse;

interface AudioContract
{
    public function speak(AudioRequest $request): AudioResponse; // text->speech
}
