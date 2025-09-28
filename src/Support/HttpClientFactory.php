<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Http;

class HttpClientFactory
{
    public static function make(array $cfg)
    {
        $client = Http::timeout((float) config('uniformed-ai.http.timeout'));

        if (!empty($cfg['base_url'])) {
            $client = $client->baseUrl(rtrim($cfg['base_url'], '/'));
        }

        if (!empty($cfg['api_key'])) {
            $client = $client->withToken($cfg['api_key']);
        }

        $retries = (int) config('uniformed-ai.http.retries', 2);
        $delay   = (int) config('uniformed-ai.http.retry_delay_ms', 250);

        if ($retries > 0) {
            $client = $client->retry($retries, $delay, throw: false);
        }

        return $client;
    }

    public static function url(array $cfg, string $path): string
    {
        $base = rtrim($cfg['base_url'] ?? '', '/');
        return $base ? $base.'/'.ltrim($path, '/') : $path;
    }
}
