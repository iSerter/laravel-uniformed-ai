<?php

namespace Iserter\UniformedAI\DTOs;

class SearchQuery
{
    public function __construct(
        public string $q,
        public int $maxResults = 5,
        public bool $includeAnswer = true,
        public ?array $filters = null,
    ) {}
}
