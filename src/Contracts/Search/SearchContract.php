<?php

namespace Iserter\UniformedAI\Contracts\Search;

use Iserter\UniformedAI\DTOs\{SearchQuery, SearchResults};

interface SearchContract
{
    public function query(SearchQuery $query): SearchResults;
}
