<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Closure;
use Generator;
use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Logging\LogDraft;
use Iserter\UniformedAI\Logging\Usage\UsageMetricsCollector;
use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};

class LoggingChatDriver extends AbstractLoggingDriver implements ChatContract
{
    public function __construct(private ChatContract $inner, string $provider)
    { parent::__construct($provider, 'chat'); }

    public function send(ChatRequest $request): ChatResponse
    {
        $draft = $this->startDraft('send', $this->requestArray($request), $request->model);
        return $this->runOperation($draft, function() use ($request, $draft) {
            $resp = $this->inner->send($request);
            $this->maybeAttachUsage($draft, $request, $resp, 'send', $resp->content, false);
            return $resp;
        }, function (ChatResponse $r) {
            return [ 'content' => $r->content, 'toolCalls' => $r->toolCalls, 'model' => $r->model ];
        });
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        $draft = $this->startDraft('stream', $this->requestArray($request), $request->model);
        $gen = $this->inner->stream($request, $onDelta);
        $capture = (bool) config('uniformed-ai.logging.stream.store_chunks', true);
        $wrapped = (function() use ($gen, $draft, $request) {
            $final = '';
            try {
                foreach ($gen as $delta) { $final .= $delta; yield $delta; }
                // success stream finalization attaches in finishSuccessStreaming; after that we can compute usage
                // We'll hook into finishSuccessStreaming via finally block? simpler: after runStreaming call.
                $this->maybeAttachUsage($draft, $request, null, 'stream', $final, false);
            } catch (\Throwable $e) {
                $this->maybeAttachUsage($draft, $request, null, 'stream', $final, true);
                throw $e;
            }
        })();
        return $this->runStreaming($draft, $wrapped, $onDelta, $capture);
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

    protected function maybeAttachUsage(LogDraft $draft, ChatRequest $request, ?ChatResponse $response, string $operation, string $finalContent, bool $wasError): void
    {
        try {
            /** @var UsageMetricsCollector $collector */
            $collector = app(UsageMetricsCollector::class);
        } catch (\Throwable $e) { return; }
        try {
            $raw = $response?->raw;
            $model = $response?->model ?? ($request->model ?? '');
            $metrics = $collector->collectChat($this->provider, $model, $request, $raw, $finalContent, $operation, $wasError);
            if ($metrics) { $draft->attachUsageMetrics($metrics->toArray()); }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
