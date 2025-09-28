<?php

namespace Iserter\UniformedAI\Services\Audio;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\Providers\ElevenLabsAudioDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class AudioManager extends Manager implements AudioContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.audio'); }

    public function speak(\Iserter\UniformedAI\Services\Audio\DTOs\AudioRequest $r): \Iserter\UniformedAI\Services\Audio\DTOs\AudioResponse { return $this->driver()->speak($r); }

    protected function createElevenlabsDriver(): AudioContract
    {
        return new ElevenLabsAudioDriver(config('uniformed-ai.providers.elevenlabs'));
    }
}
