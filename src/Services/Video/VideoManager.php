<?php

namespace Iserter\UniformedAI\Services\Video;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\Providers\{ReplicateVideoDriver, KIEVideoDriver, ElevenLabsVideoDriver, GoogleVideoDriver, KlingVideoDriver};
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;
use Iserter\UniformedAI\Logging\LoggingDriverFactory;
use Iserter\UniformedAI\Support\ServiceCatalog;

class VideoManager extends Manager implements VideoContract
{
    use SupportsUsing;

    public function getDefaultDriver() { return config('uniformed-ai.defaults.video'); }

    public function generate(\Iserter\UniformedAI\Services\Video\DTOs\VideoRequest $r): \Iserter\UniformedAI\Services\Video\DTOs\VideoResponse { return $this->driver()->generate($r); }
    public function edit(\Iserter\UniformedAI\Services\Video\DTOs\VideoRequest $r): \Iserter\UniformedAI\Services\Video\DTOs\VideoResponse { return $this->driver()->edit($r); }

    protected function createReplicateDriver(): VideoContract
    {
        return LoggingDriverFactory::wrap('video', 'replicate', new ReplicateVideoDriver(config('uniformed-ai.providers.replicate')));
    }

    protected function createKieDriver(): VideoContract
    {
        return LoggingDriverFactory::wrap('video', 'kie', new KIEVideoDriver(config('uniformed-ai.providers.kie')));
    }

    protected function createElevenlabsDriver(): VideoContract
    {
        return LoggingDriverFactory::wrap('video', 'elevenlabs', new ElevenLabsVideoDriver(config('uniformed-ai.providers.elevenlabs')));
    }

    protected function createGoogleDriver(): VideoContract
    {
        return LoggingDriverFactory::wrap('video', 'google', new GoogleVideoDriver(config('uniformed-ai.providers.google')));
    }

    protected function createKlingDriver(): VideoContract
    {
        return LoggingDriverFactory::wrap('video', 'kling', new KlingVideoDriver(config('uniformed-ai.providers.kling')));
    }

    /** @return string[] */
    public function getProviders(): array
    {
        return ServiceCatalog::providers('video');
    }

    /** @return string[] */
    public function getModels(string $provider): array
    {
        return ServiceCatalog::models('video', $provider);
    }
}
