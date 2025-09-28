<?php

namespace Iserter\UniformedAI\Services\Chat\Providers;

use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class OpenRouterChatDriver implements ChatContract
{
    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'openrouter/auto');
        $payload = [
            'model' => $model,
            'messages' => array_map(fn($m) => ['role'=>$m->role,'content'=>$m->content], $request->messages),
        ];
        $res = $http->post('chat/completions', $payload);
        if (!$res->successful()) throw new ProviderException('OpenRouter error', 'openrouter', $res->status(), $res->json());
        return new ChatResponse($res->json('choices.0.message.content') ?? '', null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        // Streaming not yet implemented for OpenRouter in this abstraction.
        yield from (function(){ if (false) yield ''; })();
    }
}
