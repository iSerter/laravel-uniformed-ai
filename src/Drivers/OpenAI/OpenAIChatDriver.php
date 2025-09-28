<?php

namespace Iserter\UniformedAI\Drivers\OpenAI;

use Closure;
use Generator;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Support\Concerns\SupportsStreaming;
use Iserter\UniformedAI\Support\RateLimiter;

class OpenAIChatDriver implements ChatContract
{
    use SupportsStreaming;

    public function __construct(private array $cfg, private ?RateLimiter $limiter = null) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $this->limiter?->throttle('openai', (int) config('uniformed-ai.rate_limit.openai'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['chat']['model'] ?? 'gpt-4.1-mini'),
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];

        if ($request->tools) {
            $payload['tools'] = array_map(fn($t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'parameters' => $t->parameters,
                ],
            ], $request->tools);
            if ($request->toolChoice) $payload['tool_choice'] = $request->toolChoice;
        }

    $res = $http->post(HttpClientFactory::url($this->cfg, 'chat/completions'), $payload);
        if (!$res->successful()) {
            throw new ProviderException($res->json('error.message') ?? 'OpenAI error', 'openai', $res->status(), $res->json());
        }

        $content = $res->json('choices.0.message.content') ?? '';
        $toolCalls = $res->json('choices.0.message.tool_calls');
        return new ChatResponse($content, $toolCalls, $payload['model'], $res->json());
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        $this->limiter?->throttle('openai', (int) config('uniformed-ai.rate_limit.openai'));
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['chat']['model'] ?? 'gpt-4.1-mini'),
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
            'stream' => true,
        ];

    $res = $http->withHeaders(['Accept' => 'text/event-stream'])->post(HttpClientFactory::url($this->cfg, 'chat/completions'), $payload);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI stream error', 'openai', $res->status(), $res->json());
        }

        yield from $this->sseToGenerator($res, $onDelta);
    }
}
