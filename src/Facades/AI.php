<?php

namespace Iserter\UniformedAI\Facades;

use Illuminate\Support\Facades\Facade;
use Iserter\UniformedAI\Managers\{ChatManager, ImageManager, AudioManager, MusicManager, SearchManager};

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
 * @method static ChatManager|\Iserter\UniformedAI\Contracts\Chat\ChatContract chat(?string $driver = null)
 * @method static ImageManager|\Iserter\UniformedAI\Contracts\Image\ImageContract image(?string $provider = null)
 * @method static AudioManager|\Iserter\UniformedAI\Contracts\Audio\AudioContract audio(?string $driver = null)
 * @method static MusicManager|\Iserter\UniformedAI\Contracts\Music\MusicContract music(?string $driver = null)
 * @method static SearchManager|\Iserter\UniformedAI\Contracts\Search\SearchContract search(?string $driver = null)
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iserter.uniformed-ai.facade';
    }
}
