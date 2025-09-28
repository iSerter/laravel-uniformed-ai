<?php

namespace Iserter\UniformedAI\Services\Search\DTOs;

class SearchResults
{
    public function __construct(
        public ?string $answer,
        /** @var array<int, array{title:string,url:string,snippet?:string,score?:float}> */
        public array $results,
        public ?array $raw = null,
    ) {}
}
