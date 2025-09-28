<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Music\MusicContract;
use Iserter\UniformedAI\Drivers\PIAPI\PIAPIMusicDriver;

class MusicManager extends Manager implements MusicContract
{
    public function getDefaultDriver() { return config('uniformed-ai.defaults.music'); }

    public function compose($r) { return $this->driver()->compose($r); }

    protected function createPiapiDriver(): MusicContract
    {
        return new PIAPIMusicDriver(config('uniformed-ai.providers.piapi'));
    }
}
