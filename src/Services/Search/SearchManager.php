<?php

namespace Iserter\UniformedAI\Services\Search;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Search\Contracts\SearchContract;
use Iserter\UniformedAI\Services\Search\Providers\TavilySearchDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;
use Iserter\UniformedAI\Logging\LoggingDriverFactory;
use Iserter\UniformedAI\Support\ServiceCatalog;

class SearchManager extends Manager implements SearchContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.search'); }

    public function query(\Iserter\UniformedAI\Services\Search\DTOs\SearchQuery $q): \Iserter\UniformedAI\Services\Search\DTOs\SearchResults { return $this->driver()->query($q); }

    protected function createTavilyDriver(): SearchContract
    {
        return LoggingDriverFactory::wrap('search', 'tavily', new TavilySearchDriver(config('uniformed-ai.providers.tavily')));
    }

    /** @return string[] */
    public function getProviders(): array
    {
        return ServiceCatalog::providers('search');
    }

    /** @return string[] */
    public function getModels(string $provider): array
    {
        return ServiceCatalog::models('search', $provider);
    }
}
