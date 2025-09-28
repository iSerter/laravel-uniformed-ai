<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Search\SearchContract;
use Iserter\UniformedAI\DTOs\{SearchQuery, SearchResults};
use Iserter\UniformedAI\Drivers\Tavily\TavilySearchDriver;

class SearchManager extends Manager implements SearchContract
{
    public function getDefaultDriver() { return config('uniformed-ai.defaults.search'); }

    public function query(SearchQuery $q): SearchResults { return $this->driver()->query($q); }

    protected function createTavilyDriver(): SearchContract
    {
        $cfg = config('uniformed-ai.providers.tavily');
        if (empty($cfg['base_url'])) { $cfg['base_url'] = 'https://api.tavily.com'; }
        return new TavilySearchDriver($cfg);
    }
}
