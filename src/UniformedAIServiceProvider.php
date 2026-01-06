<?php

namespace Iserter\UniformedAI;

use Illuminate\Support\ServiceProvider;
use Iserter\UniformedAI\Services\Chat\ChatManager;
use Iserter\UniformedAI\Services\Image\ImageManager;
use Iserter\UniformedAI\Services\Audio\AudioManager;
use Iserter\UniformedAI\Services\Music\MusicManager;
use Iserter\UniformedAI\Services\Search\SearchManager;
use Iserter\UniformedAI\Services\Video\VideoManager;
use Iserter\UniformedAI\Logging\Commands\PruneServiceUsageLogs;
use Iserter\UniformedAI\Logging\Usage\{UsageMetricsCollector, ProviderUsageExtractor, HeuristicCl100kEstimator, PricingEngine};
use Iserter\UniformedAI\Support\PricingRepository;

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
    $this->app->singleton(VideoManager::class, fn($app) => new VideoManager($app));

        // Usage metrics dependencies (scoped singletons for lightweight objects)
        $this->app->singleton(ProviderUsageExtractor::class, fn() => new ProviderUsageExtractor());
        $this->app->singleton(HeuristicCl100kEstimator::class, function() {
            return new HeuristicCl100kEstimator();
        });
        $this->app->singleton(PricingEngine::class, fn($app) => new PricingEngine($app->make(PricingRepository::class)));
        $this->app->singleton(UsageMetricsCollector::class, function($app) {
            return new UsageMetricsCollector(
                $app->make(ProviderUsageExtractor::class),
                $app->make(HeuristicCl100kEstimator::class),
                $app->make(PricingEngine::class),
            );
        });

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

                public function video(?string $driver = null) {
                    $manager = $this->app->make(VideoManager::class);
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

        // Lightweight config validation for usage metrics
        $usageCfg = config('uniformed-ai.logging.usage', []);
        if (!is_array($usageCfg)) {
            logger()->warning('uniformed-ai: logging.usage config malformed (not array)');
        } else {
            $rate = $usageCfg['sampling']['success_rate'] ?? 1.0;
            if (!is_numeric($rate) || $rate < 0 || $rate > 1) {
                logger()->warning('uniformed-ai: usage sampling.success_rate must be between 0 and 1');
            }
        }

        // Auto-load migrations from package (only if not already published)
        // Check if migrations have been published to avoid duplicate loading
        $migrationsPath = database_path('migrations');
        $hasPublishedMigrations = false;
        
        if (is_dir($migrationsPath)) {
            // Check if either migration has been published
            $usageLogsFiles = glob($migrationsPath . '/*_create_service_usage_logs_table.php');
            $pricingsFiles = glob($migrationsPath . '/*_create_service_pricings_table.php');
            $hasPublishedMigrations = !empty($usageLogsFiles) || !empty($pricingsFiles);
        }
        
        // Only auto-load from vendor if migrations haven't been published
        // This allows users to customize migrations by publishing them
        // Note: If you publish migrations, publish ALL of them to avoid conflicts
        if (! $hasPublishedMigrations) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        // Publish migrations (for users who want to customize them)
        if (! $hasPublishedMigrations) {
            $timestamp = date('Y_m_d_His');
            $this->publishes([
                __DIR__.'/../database/migrations/2025_01_01_000000_create_service_usage_logs_table.php' => database_path("migrations/{$timestamp}_create_service_usage_logs_table.php"),
                __DIR__.'/../database/migrations/2025_01_02_000000_create_service_pricings_table.php' => database_path("migrations/{$timestamp}_create_service_pricings_table.php"),
            ], 'uniformed-ai-migrations');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneServiceUsageLogs::class,
            ]);
        }
    }
}
