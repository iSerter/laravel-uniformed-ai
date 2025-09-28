<?php

namespace Iserter\UniformedAI\Support\Concerns;

trait SupportsStreaming
{
    protected function sseToGenerator($response, ?\Closure $onDelta = null): \Generator
    {
        foreach (explode("\n\n", $response->body()) as $chunk) {
            $line = trim($chunk);
            if ($line === '' || !str_starts_with($line, 'data:')) continue;
            $json = substr($line, 5);
            $delta = json_decode($json, true);
            $text = $delta['choices'][0]['delta']['content'] ?? '';
            if ($onDelta) $onDelta($text, $delta);
            yield $text;
        }
    }
}
