<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\DTOs\SearchQuery;
use Iserter\UniformedAI\Managers\SearchManager;

it('queries Tavily search', function() {
    config()->set('uniformed-ai.defaults.search', 'tavily');
    config()->set('uniformed-ai.providers.tavily.api_key', 'tv-key');

    Http::fake([
        'api.tavily.com/*' => Http::response([
            'answer' => 'PHP 8.3 adds cool stuff',
            'results' => [
                ['title' => 'Blog', 'url' => 'https://example.com', 'content' => 'Snippet']
            ]
        ], 200)
    ]);

    $manager = app(SearchManager::class);
    $resp = $manager->query(new SearchQuery('php 8.3 features'));

    expect($resp->answer)->toContain('PHP 8.3');
    expect($resp->results)->toHaveCount(1);
});
