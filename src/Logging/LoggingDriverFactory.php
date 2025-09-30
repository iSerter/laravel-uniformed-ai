<?php

namespace Iserter\UniformedAI\Logging;

use Iserter\UniformedAI\Logging\Decorators\{LoggingChatDriver, LoggingImageDriver, LoggingAudioDriver, LoggingMusicDriver, LoggingSearchDriver};
use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Music\Contracts\MusicContract;
use Iserter\UniformedAI\Services\Search\Contracts\SearchContract;

class LoggingDriverFactory
{
    public static function wrap(string $service, string $provider, object $driver): object
    {
        if (!config('uniformed-ai.logging.enabled', true)) return $driver;
        // future: per-service toggle
        return match($service) {
            'chat' => $driver instanceof ChatContract ? new LoggingChatDriver($driver, $provider) : $driver,
            'image' => $driver instanceof ImageContract ? new LoggingImageDriver($driver, $provider) : $driver,
            'audio' => $driver instanceof AudioContract ? new LoggingAudioDriver($driver, $provider) : $driver,
            'music' => $driver instanceof MusicContract ? new LoggingMusicDriver($driver, $provider) : $driver,
            'search' => $driver instanceof SearchContract ? new LoggingSearchDriver($driver, $provider) : $driver,
            default => $driver,
        };
    }
}
