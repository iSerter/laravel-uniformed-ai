<?php

namespace Iserter\UniformedAI\Services\Search\Contracts;

use Iserter\UniformedAI\Services\Search\DTOs\SearchQuery;
use Iserter\UniformedAI\Services\Search\DTOs\SearchResults;

interface SearchContract
{
    public function query(SearchQuery $query): SearchResults;
}
