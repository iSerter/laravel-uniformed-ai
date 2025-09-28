<?php

namespace Iserter\UniformedAI\Services\Search;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Search\Contracts\SearchContract;
use Iserter\UniformedAI\Services\Search\Providers\TavilySearchDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class SearchManager extends Manager implements SearchContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.search'); }

    public function query(\Iserter\UniformedAI\Services\Search\DTOs\SearchQuery $q): \Iserter\UniformedAI\Services\Search\DTOs\SearchResults { return $this->driver()->query($q); }

    protected function createTavilyDriver(): SearchContract
    {
        return new TavilySearchDriver(config('uniformed-ai.providers.tavily'));
    }
}
