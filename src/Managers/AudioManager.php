<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Audio\AudioContract;
use Iserter\UniformedAI\Drivers\ElevenLabs\ElevenLabsAudioDriver;

class AudioManager extends Manager implements AudioContract
{
    public function getDefaultDriver() { return config('uniformed-ai.defaults.audio'); }

    public function speak($r) { return $this->driver()->speak($r); }

    protected function createElevenlabsDriver(): AudioContract
    {
        return new ElevenLabsAudioDriver(config('uniformed-ai.providers.elevenlabs'));
    }
}
