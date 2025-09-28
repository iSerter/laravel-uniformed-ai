<?php

namespace Iserter\UniformedAI\Services\Search\DTOs;

class SearchQuery
{
    public function __construct(
        public string $q,
        public int $maxResults = 5,
        public bool $includeAnswer = true,
        public ?array $filters = null,
    ) {}
}
