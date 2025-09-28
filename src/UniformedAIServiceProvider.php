<?php

namespace Iserter\UniformedAI;

use Illuminate\Support\ServiceProvider;
use Iserter\UniformedAI\Managers\{ChatManager, ImageManager, AudioManager, MusicManager, SearchManager};

class UniformedAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/uniformed-ai.php', 'uniformed-ai');

        $this->app->singleton(ChatManager::class, fn($app) => new ChatManager($app));
        $this->app->singleton(ImageManager::class, fn($app) => new ImageManager($app));
        $this->app->singleton(AudioManager::class, fn($app) => new AudioManager($app));
        $this->app->singleton(MusicManager::class, fn($app) => new MusicManager($app));
        $this->app->singleton(SearchManager::class, fn($app) => new SearchManager($app));

        // Facade accessor binding
        $this->app->singleton('iserter.uniformed-ai.facade', function ($app) {
            return new class($app) {
                public function __construct(private $app) {}
                public function chat()  { return $this->app->make(ChatManager::class); }
                public function image() { return $this->app->make(ImageManager::class); }
                public function audio() { return $this->app->make(AudioManager::class); }
                public function music() { return $this->app->make(MusicManager::class); }
                public function search(){ return $this->app->make(SearchManager::class); }
            };
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/uniformed-ai.php' => config_path('uniformed-ai.php'),
        ], 'uniformed-ai-config');
    }
}
