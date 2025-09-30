<?php

namespace Iserter\UniformedAI\Logging;

use Illuminate\Support\Str;

trait SanitizesPayloads
{
    protected function sanitize(array $data, string $type = 'request'): array
    {
        $mask = config('uniformed-ai.logging.redaction.mask', '***REDACTED***');
        $patterns = $this->secretPatterns();
        $redactKeys = ['api_key','authorization','auth','secret','token','key','password','access_token','bearer'];
        $maxChars = (int) config('uniformed-ai.logging.truncate.' . ($type === 'request' ? 'request_chars' : 'response_chars'), 20000);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) return $data; // give up

        $arr = json_decode($json, true);
        $walk = function (&$value, $key) use (&$walk, $mask, $patterns, $redactKeys) {
            if (is_array($value)) {
                foreach ($value as $k => &$v) { $walk($v, $k); }
                return;
            }
            if (is_string($value)) {
                if (in_array(strtolower((string) $key), $redactKeys, true)) {
                    $value = $mask; return;
                }
                foreach ($patterns as $p) {
                    if (@preg_match($p, $value) === 1) { $value = $mask; return; }
                }
                // heuristic: long high-entropy string
                if (strlen($value) >= 32 && $this->looksLikeSecret($value)) { $value = $mask; return; }
            }
        };
        foreach ($arr as $k => &$v) { $walk($v, $k); }

        // Truncate large serialized string while keeping structure: encode again if too large
        $encoded = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && strlen($encoded) > $maxChars) {
            // naive truncation with indication; decode back to array with truncated marker at end string value
            $truncated = substr($encoded, 0, $maxChars - 15) . '...(truncated)';
            // attempt to decode - if fails, just return arr (already sanitized) with note
            $decoded = json_decode($truncated, true);
            if (is_array($decoded)) return $decoded;
            // fallback: store as single field
            return ['data' => substr($encoded, 0, $maxChars - 15) . '...(truncated)'];
        }
        return $arr;
    }

    protected function truncateChunk(string $delta): string
    {
        $limit = (int) config('uniformed-ai.logging.truncate.chunk_chars', 2000);
        return Str::limit($delta, $limit, '...(truncated)');
    }

    protected function secretPatterns(): array
    {
        static $compiled = null; if ($compiled !== null) return $compiled;
        $compiled = [
            '/sk-[A-Za-z0-9]{20,}/',
            '/^AIza[\w-]{30,}$/',
            '/\b[0-9a-fA-F]{32,64}\b/',
            '/xox[baprs]-[A-Za-z0-9-]{10,}/',
        ];
        return $compiled;
    }

    protected function looksLikeSecret(string $value): bool
    {
        // Simple entropy-ish heuristic: mostly base64url / hex characters and few vowels
        $plain = preg_replace('/[^A-Za-z0-9]/', '', $value);
        if (strlen($plain) < 24) return false;
        $vowels = preg_match_all('/[aeiouAEIOU]/', $plain);
        return $vowels / max(strlen($plain),1) < 0.15; // low vowel ratio
    }
}
