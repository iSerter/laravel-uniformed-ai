<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Audio\AudioContract;
use Iserter\UniformedAI\Drivers\ElevenLabs\ElevenLabsAudioDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class AudioManager extends Manager implements AudioContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.audio'); }

    public function speak(\Iserter\UniformedAI\DTOs\AudioRequest $r): \Iserter\UniformedAI\DTOs\AudioResponse { return $this->driver()->speak($r); }

    protected function createElevenlabsDriver(): AudioContract
    {
        return new ElevenLabsAudioDriver(config('uniformed-ai.providers.elevenlabs'));
    }
}
