<?php

namespace Iserter\UniformedAI\Services\Audio;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\Providers\ElevenLabsAudioDriver;
use Iserter\UniformedAI\Services\Audio\Providers\ReplicateAudioDriver;
use Iserter\UniformedAI\Services\Audio\Providers\OpenAIAudioDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;
use Iserter\UniformedAI\Logging\LoggingDriverFactory;
use Iserter\UniformedAI\Support\ServiceCatalog;

class AudioManager extends Manager implements AudioContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.audio'); }

    public function speak(\Iserter\UniformedAI\Services\Audio\DTOs\AudioRequest $r): \Iserter\UniformedAI\Services\Audio\DTOs\AudioResponse { return $this->driver()->speak($r); }

    public function transcribe(\Iserter\UniformedAI\Services\Audio\DTOs\AudioTranscriptionRequest $r): \Iserter\UniformedAI\Services\Audio\DTOs\AudioTranscriptionResponse { return $this->driver()->transcribe($r); }

    /**
     * Get available voices for the default or specified provider.
     */
    public function getAvailableVoices(bool $refresh = false, ?string $provider = null): array
    {
        $provider = $provider ?: $this->getDefaultDriver();
        return $this->driver($provider)->getAvailableVoices($refresh);
    }

    protected function createOpenaiDriver(): AudioContract
    {
        return LoggingDriverFactory::wrap('audio', 'openai', new OpenAIAudioDriver(config('uniformed-ai.providers.openai')));
    }

    protected function createElevenlabsDriver(): AudioContract
    {
        return LoggingDriverFactory::wrap('audio', 'elevenlabs', new ElevenLabsAudioDriver(config('uniformed-ai.providers.elevenlabs')));
    }

    protected function createReplicateDriver(): AudioContract
    {
        return LoggingDriverFactory::wrap('audio', 'replicate', new ReplicateAudioDriver(config('uniformed-ai.providers.replicate')));
    }

    /** @return string[] */
    public function getProviders(): array
    {
        return ServiceCatalog::providers('audio');
    }

    /** @return string[] */
    public function getModels(string $provider): array
    {
        return ServiceCatalog::models('audio', $provider);
    }
}
