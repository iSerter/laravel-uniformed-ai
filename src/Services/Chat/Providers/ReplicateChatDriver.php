<?php

namespace Iserter\UniformedAI\Services\Chat\Providers;

use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

/**
 * Minimal Replicate Chat driver (prediction-based). Replicate exposes a general predictions API; we
 * treat chat as creating a prediction with a text prompt aggregated from messages.
 * NOTE: This is a synchronous convenience implementation; streaming not yet implemented.
 */
class ReplicateChatDriver implements ChatContract
{
    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'replicate');
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? null);
        if (!$model) {
            throw new ProviderException('Replicate chat model not configured', 'replicate', 400, []);
        }

        // Combine messages into a single prompt with role labels (simple strategy)
        $prompt = collect($request->messages)
            ->map(fn($m) => strtoupper($m->role).": ".$m->content)
            ->implode("\n");

        $payload = [
            'version' => $model, // For official replicate style version references; if user supplies owner/model:tag it still passes
            'input' => [
                'prompt' => $prompt,
                // Optionally map temperature if present (not all models use same param)
                'temperature' => $request->temperature,
            ],
        ];
        // Remove nulls recursively
        $payload['input'] = array_filter($payload['input'], fn($v) => !is_null($v));

        $res = $http->post('predictions', $payload);
        if (!$res->successful()) {
            $raw = $res->json();
            if (is_array($raw)) { $raw['__http_status'] = $res->status(); }
            throw new ProviderException($raw['error'] ?? 'Replicate prediction error', 'replicate', $res->status(), $raw);
        }

        $raw = $res->json();
        if (is_array($raw)) { $raw['__http_status'] = $res->status(); }
        $output = $res->json('output');

        // Some models return array outputs, join lines; others return string
        if (is_array($output)) {
            $content = trim(collect($output)->join("\n"));
        } else {
            $content = (string) $output;
        }

        return new ChatResponse($content, null, $model, $raw);
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        // Replicate streaming would use the prediction stream URL (SSE). Not implemented yet.
        yield from (function(){ if (false) yield ''; })();
    }
}
