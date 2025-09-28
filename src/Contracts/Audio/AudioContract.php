<?php

namespace Iserter\UniformedAI\Contracts\Audio;

use Iserter\UniformedAI\DTOs\{AudioRequest, AudioResponse};

interface AudioContract
{
    public function speak(AudioRequest $request): AudioResponse; // text->speech
}
