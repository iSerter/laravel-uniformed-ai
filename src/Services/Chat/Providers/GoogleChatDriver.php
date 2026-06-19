<?php

namespace Iserter\UniformedAI\Services\Chat\Providers;

use Closure;
use Generator;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;

class GoogleChatDriver implements ChatContract
{
    public function __construct(private array $cfg)
    {
    }

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'google');
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'gemini-1.5-pro');
        $contents = array_map(fn ($m) => [
            'role' => $m->role,
            'parts' => [['text' => $m->content]],
        ], $request->messages);

        $payload = ['contents' => $contents];

        if ($request->options) {
            $payload = array_merge($payload, $request->options);
        }

        $res = $http->post("v1beta/models/{$model}:generateContent", $payload);
        if (!$res->successful()) {
            throw new ProviderException('Google AI error', 'google', $res->status(), $res->json());
        }

        $text = $res->json('candidates.0.content.parts.0.text') ?? '';
        return new ChatResponse($text, null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        yield from (function () { if (false) { yield ''; } })();
    }
}
