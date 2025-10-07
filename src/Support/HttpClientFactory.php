<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Http;

class HttpClientFactory
{
    /**
     * Build a configured HTTP client for a provider.
     * Strategy summary:
     *  - openai, openrouter, replicate, kie, piapi => Authorization: Bearer <key>
     *  - elevenlabs => xi-api-key header only
     *  - google => key placed by caller as query param (no auth header here)
     *  - tavily => key placed by caller in JSON body (no auth header)
     *  - default (unknown) => Bearer token if api_key present
     */
    public static function make(array $cfg, ?string $provider = null)
    {
        $client = Http::timeout((float) config('uniformed-ai.http.timeout'));

        if (!empty($cfg['base_url'])) {
            $client = $client->baseUrl(rtrim($cfg['base_url'], '/'));
        }

        $providerSlug = $provider ? strtolower($provider) : null;

        if (!empty($cfg['api_key'])) {
            $key = $cfg['api_key'];
            switch ($providerSlug) {
                case 'elevenlabs':
                    // ElevenLabs uses custom header; do NOT also send Bearer to avoid confusion
                    $client = $client->withHeaders(['xi-api-key' => $key]);
                    break;
                case 'google': // handled via query param at call site
                case 'tavily': // handled via JSON body at call site
                    // Intentionally skip Authorization header
                    break;
                case null: // Backwards compatibility when not supplying provider
                    $client = $client->withToken($key);
                    break;
                default: // standard Bearer token pattern
                    $client = $client->withToken($key);
            }
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
