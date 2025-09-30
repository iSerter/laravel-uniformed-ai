<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Search\Contracts\SearchContract;
use Iserter\UniformedAI\Services\Search\DTOs\{SearchQuery, SearchResults};

class LoggingSearchDriver extends AbstractLoggingDriver implements SearchContract
{
    public function __construct(private SearchContract $inner, string $provider)
    { parent::__construct($provider, 'search'); }

    public function query(SearchQuery $q): SearchResults
    {
        $draft = $this->startDraft('query', $this->req($q), null);
        return $this->runOperation(
            $draft,
            fn() => $this->inner->query($q),
            fn(SearchResults $r) => [
                'answer' => $r->answer,
                'results' => array_map(fn($res) => [
                    'title' => $res['title'] ?? '',
                    'url' => $res['url'] ?? '',
                ], array_slice($r->results, 0, 10)),
            ]
        );
    }

    protected function req(SearchQuery $q): array
    { return ['q' => $q->q, 'maxResults' => $q->maxResults, 'includeAnswer' => $q->includeAnswer]; }
}
