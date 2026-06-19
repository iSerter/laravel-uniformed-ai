<?php

namespace Iserter\UniformedAI\Logging;

use Illuminate\Support\Str;

trait SanitizesPayloads
{
    protected function sanitize(array $data, string $type = 'request'): array
    {
        $mask = config('uniformed-ai.logging.redaction.mask', '***REDACTED***');
        $patterns = $this->secretPatterns();
        $redactKeys = ['api_key', 'authorization', 'auth', 'secret', 'token', 'key', 'password', 'access_token', 'bearer'];
        $maxChars = (int) config('uniformed-ai.logging.truncate.' . ($type === 'request' ? 'request_chars' : 'response_chars'), 20000);

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return $data;
        } // give up

        $arr = json_decode($json, true);
        $walk = function (&$value, $key) use (&$walk, $mask, $patterns, $redactKeys) {
            if (is_array($value)) {
                foreach ($value as $k => &$v) {
                    $walk($v, $k);
                }
                return;
            }
            if (is_string($value)) {
                if (in_array(strtolower((string) $key), $redactKeys, true)) {
                    $value = $mask;
                    return;
                }
                foreach ($patterns as $p) {
                    if (@preg_match($p, $value) === 1) {
                        $value = $mask;
                        return;
                    }
                }
                // heuristic: long high-entropy string
                if (strlen($value) >= 32 && $this->looksLikeSecret($value)) {
                    $value = $mask;
                    return;
                }
            }
        };
        foreach ($arr as $k => &$v) {
            $walk($v, $k);
        }

        // Truncate large payloads by shortening individual string values to preserve JSON structure
        $encoded = json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded !== false && strlen($encoded) > $maxChars) {
            $arr = $this->truncateArrayValues($arr, $maxChars);
        }

        return $arr;
    }

    /**
     * Recursively truncate long string values in an array to bring total size under limit.
     * Preserves JSON structure by only shortening individual string values.
     */
    protected function truncateArrayValues(array $arr, int $maxChars): array
    {
        // Collect all string values with their paths and lengths
        $strings = [];
        $this->collectStrings($arr, [], $strings);

        // Sort by length descending — truncate longest strings first
        usort($strings, fn ($a, $b) => $b['length'] - $a['length']);

        foreach ($strings as $entry) {
            $currentSize = strlen(json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
            if ($currentSize <= $maxChars) {
                break;
            }

            $excess = $currentSize - $maxChars;
            $path = $entry['path'];
            $value = $this->getNestedValue($arr, $path);

            if (!is_string($value) || strlen($value) <= 50) {
                continue;
            }

            $newLength = max(50, strlen($value) - $excess - 15);
            $this->setNestedValue($arr, $path, substr($value, 0, $newLength) . '...(truncated)');
        }

        return $arr;
    }

    private function collectStrings(array $arr, array $path, array &$result): void
    {
        foreach ($arr as $key => $value) {
            $currentPath = array_merge($path, [$key]);
            if (is_array($value)) {
                $this->collectStrings($value, $currentPath, $result);
            } elseif (is_string($value) && strlen($value) > 50) {
                $result[] = ['path' => $currentPath, 'length' => strlen($value)];
            }
        }
    }

    private function getNestedValue(array $arr, array $path): mixed
    {
        $current = $arr;
        foreach ($path as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }
        return $current;
    }

    private function setNestedValue(array &$arr, array $path, mixed $value): void
    {
        $current = &$arr;
        foreach ($path as $i => $key) {
            if ($i === count($path) - 1) {
                $current[$key] = $value;
                return;
            }
            $current = &$current[$key];
        }
    }

    protected function truncateChunk(string $delta): string
    {
        $limit = (int) config('uniformed-ai.logging.truncate.chunk_chars', 2000);
        return Str::limit($delta, $limit, '...(truncated)');
    }

    protected function secretPatterns(): array
    {
        static $compiled = null;
        if ($compiled !== null) {
            return $compiled;
        }
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
        if (strlen($plain) < 24) {
            return false;
        }
        $vowels = preg_match_all('/[aeiouAEIOU]/', $plain);
        return $vowels / max(strlen($plain), 1) < 0.15; // low vowel ratio
    }
}
