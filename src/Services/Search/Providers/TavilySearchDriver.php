<?php

namespace Iserter\UniformedAI\Services\Search\Providers;

use Iserter\UniformedAI\Services\Search\Contracts\SearchContract;
use Iserter\UniformedAI\Services\Search\DTOs\{SearchQuery, SearchResults};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class TavilySearchDriver implements SearchContract
{
    public function __construct(private array $cfg) {}

    public function query(SearchQuery $q): SearchResults
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'api_key' => $this->cfg['api_key'] ?? null,
            'query' => $q->q,
            'max_results' => $q->maxResults,
            'include_answer' => $q->includeAnswer,
            'search_depth' => 'advanced',
        ];
        $res = $http->post('search', $payload);
        if (!$res->successful()) throw new ProviderException('Tavily error', 'tavily', $res->status(), $res->json());
        $answer = $res->json('answer');
        $results = array_map(fn($r) => [
            'title' => $r['title'] ?? ($r['url'] ?? 'Result'),
            'url' => $r['url'],
            'snippet' => $r['content'] ?? null,
        ], $res->json('results') ?? []);
        return new SearchResults($answer, $results, $res->json());
    }
}
