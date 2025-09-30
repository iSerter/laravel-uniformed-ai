<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Closure;
use Generator;
use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Logging\LogDraft;
use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};

class LoggingChatDriver extends AbstractLoggingDriver implements ChatContract
{
    public function __construct(private ChatContract $inner, string $provider)
    { parent::__construct($provider, 'chat'); }

    public function send(ChatRequest $request): ChatResponse
    {
        $draft = $this->startDraft('send', $this->requestArray($request), $request->model);
        return $this->runOperation($draft, fn() => $this->inner->send($request), function (ChatResponse $r) {
            return [
                'content' => $r->content,
                'toolCalls' => $r->toolCalls,
                'model' => $r->model,
            ];
        });
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        $draft = $this->startDraft('stream', $this->requestArray($request), $request->model);
        $gen = $this->inner->stream($request, $onDelta);
        $capture = (bool) config('uniformed-ai.logging.stream.store_chunks', true);
        return $this->runStreaming($draft, $gen, $onDelta, $capture);
    }

    protected function requestArray(ChatRequest $r): array
    {
        return [
            'model' => $r->model,
            'messages' => array_map(fn($m) => ['role' => $m->role, 'content' => $m->content], $r->messages),
            'temperature' => $r->temperature,
            'maxTokens' => $r->maxTokens,
            'toolChoice' => $r->toolChoice,
            'tools' => $r->tools ? array_map(fn($t) => ['name' => $t->name], $r->tools) : null,
        ];
    }
}
