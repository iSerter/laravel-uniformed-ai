<?php

namespace Iserter\UniformedAI;

use Illuminate\Support\ServiceProvider;
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Services\Image\ImageManager;
use Iserter\UniformedAI\Services\Audio\AudioManager;
use Iserter\UniformedAI\Services\Music\MusicManager;
use Iserter\UniformedAI\Services\Search\SearchManager;

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

                /**
                 * Get the chat manager or a specific chat driver when $driver provided.
                 */
                public function chat(?string $driver = null)  {
                    $manager = $this->app->make(ChatManager::class);
                    return $driver ? $manager->driver($driver) : $manager;
                }

                /**
                 * Get the image manager or a specific image driver when $provider provided.
                 * Parameter named $provider to allow named argument calls: AI::image(provider: 'openai')
                 */
                public function image(?string $provider = null) {
                    $manager = $this->app->make(ImageManager::class);
                    return $provider ? $manager->driver($provider) : $manager;
                }

                public function audio(?string $driver = null) {
                    $manager = $this->app->make(AudioManager::class);
                    return $driver ? $manager->driver($driver) : $manager;
                }

                public function music(?string $driver = null) {
                    $manager = $this->app->make(MusicManager::class);
                    return $driver ? $manager->driver($driver) : $manager;
                }

                public function search(?string $driver = null) {
                    $manager = $this->app->make(SearchManager::class);
                    return $driver ? $manager->driver($driver) : $manager;
                }
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
