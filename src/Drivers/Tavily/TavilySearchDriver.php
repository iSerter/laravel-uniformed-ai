<?php

namespace Iserter\UniformedAI\Drivers\Tavily;

use Iserter\UniformedAI\Contracts\Search\SearchContract;
use Iserter\UniformedAI\DTOs\{SearchQuery, SearchResults};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\RateLimiter;

class TavilySearchDriver implements SearchContract
{
    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function query(SearchQuery $q): SearchResults
    {
        $this->limiter?->throttle('tavily', (int) config('uniformed-ai.rate_limit.tavily'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'api_key' => $this->cfg['api_key'] ?? null,
            'query' => $q->q,
            'max_results' => $q->maxResults,
            'include_answer' => $q->includeAnswer,
            'search_depth' => 'advanced',
        ];
    $res = $http->post(HttpClientFactory::url($this->cfg, 'search'), $payload);
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
