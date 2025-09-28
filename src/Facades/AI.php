<?php

namespace Iserter\UniformedAI\Facades;

use Illuminate\Support\Facades\Facade;
use Iserter\UniformedAI\Managers\{ChatManager, ImageManager, AudioManager, MusicManager, SearchManager};

/**
 * @method static ChatManager chat()
 * @method static ImageManager image()
 * @method static AudioManager audio()
 * @method static MusicManager music()
 * @method static SearchManager search()
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iserter.uniformed-ai.facade';
    }
}
