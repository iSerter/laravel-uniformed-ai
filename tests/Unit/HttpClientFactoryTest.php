<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Support\HttpClientFactory;

it('applies bearer token for openai', function() {
    Http::fake(['api.openai.com/*' => Http::response(['ok'=>true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://api.openai.com/v1', 'api_key' => 'sk-test'], 'openai');
    $client->get('models');
    Http::assertSent(function($req){
        return $req->hasHeader('Authorization') && str_contains($req->header('Authorization')[0], 'Bearer sk-test');
    });
});

it('applies custom xi header for elevenlabs and not bearer', function() {
    Http::fake(['api.elevenlabs.io/*' => Http::response(['ok'=>true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://api.elevenlabs.io', 'api_key' => 'xi-key'], 'elevenlabs');
    $client->get('status');
    Http::assertSent(function($req){
        return $req->hasHeader('xi-api-key') && !$req->hasHeader('Authorization');
    });
});

it('skips auth header for tavily (key in body expected)', function() {
    Http::fake(['api.tavily.com/*' => Http::response(['ok'=>true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://api.tavily.com', 'api_key' => 'tv-key'], 'tavily');
    $client->post('search', ['query' => 'php']);
    Http::assertSent(function($req){
        return !$req->hasHeader('Authorization');
    });
});

it('skips auth header for google (query param used)', function() {
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['ok'=>true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://generativelanguage.googleapis.com', 'api_key' => 'g-key'], 'google');
    $client->get('v1beta/test?key=g-key');
    Http::assertSent(function($req){
        return !$req->hasHeader('Authorization');
    });
});

it('falls back to bearer when provider unspecified', function() {
    Http::fake(['api.example.com/*' => Http::response(['ok'=>true], 200)]);
    $client = HttpClientFactory::make(['base_url' => 'https://api.example.com', 'api_key' => 'abc']);
    $client->get('ping');
    Http::assertSent(function($req){
        return $req->hasHeader('Authorization');
    });
});
