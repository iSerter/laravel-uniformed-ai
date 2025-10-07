<?php

namespace Iserter\UniformedAI\Services\Music;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Music\Contracts\MusicContract;
use Iserter\UniformedAI\Services\Music\Providers\PIAPIMusicDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;
use Iserter\UniformedAI\Logging\LoggingDriverFactory;
use Iserter\UniformedAI\Support\ServiceCatalog;

class MusicManager extends Manager implements MusicContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.music'); }

    public function compose(\Iserter\UniformedAI\Services\Music\DTOs\MusicRequest $r): \Iserter\UniformedAI\Services\Music\DTOs\MusicResponse { return $this->driver()->compose($r); }

    protected function createPiapiDriver(): MusicContract
    {
        return LoggingDriverFactory::wrap('music', 'piapi', new PIAPIMusicDriver(config('uniformed-ai.providers.piapi')));
    }

    /** @return string[] */
    public function getProviders(): array
    {
        return ServiceCatalog::providers('music');
    }

    /** @return string[] */
    public function getModels(string $provider): array
    {
        return ServiceCatalog::models('music', $provider);
    }
}
