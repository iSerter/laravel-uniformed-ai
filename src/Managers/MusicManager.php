<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Music\MusicContract;
use Iserter\UniformedAI\Drivers\PIAPI\PIAPIMusicDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class MusicManager extends Manager implements MusicContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.music'); }

    public function compose(\Iserter\UniformedAI\DTOs\MusicRequest $r): \Iserter\UniformedAI\DTOs\MusicResponse { return $this->driver()->compose($r); }

    protected function createPiapiDriver(): MusicContract
    {
        return new PIAPIMusicDriver(config('uniformed-ai.providers.piapi'));
    }
}
