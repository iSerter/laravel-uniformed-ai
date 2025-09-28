<?php

namespace Iserter\UniformedAI\Facades;

use Illuminate\Support\Facades\Facade;
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Services\Image\ImageManager;
use Iserter\UniformedAI\Services\Audio\AudioManager;
use Iserter\UniformedAI\Services\Music\MusicManager;
use Iserter\UniformedAI\Services\Search\SearchManager;

/**
 * Dynamic access to AI services.
 *
 * When called without arguments returns the Manager (supports extension, etc.):
 *   AI::chat()->send(...)
 *
 * When called with a driver/provider string returns the underlying driver instance directly:
 *   AI::chat('openrouter')->send(...)
 *   AI::image(provider: 'openai')->create(...)
 *
 * @method static ChatManager|\Iserter\UniformedAI\Services\Chat\Contracts\ChatContract chat(?string $driver = null)
 * @method static ImageManager|\Iserter\UniformedAI\Services\Image\Contracts\ImageContract image(?string $provider = null)
 * @method static AudioManager|\Iserter\UniformedAI\Services\Audio\Contracts\AudioContract audio(?string $driver = null)
 * @method static MusicManager|\Iserter\UniformedAI\Services\Music\Contracts\MusicContract music(?string $driver = null)
 * @method static SearchManager|\Iserter\UniformedAI\Services\Search\Contracts\SearchContract search(?string $driver = null)
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iserter.uniformed-ai.facade';
    }
}
