<?php

namespace Iserter\UniformedAI\Logging;

use Closure;
use Generator;

abstract class AbstractLoggingDriver
{
    public function __construct(protected string $provider, protected string $service) {}

    protected function startDraft(?string $operation, array $request, ?string $model = null): LogDraft
    {
        return LogDraft::start($this->service, $this->provider, $operation, $request, $model);
    }

    /**
     * @template TResponse
     * @param callable():TResponse $execute
     * @param LogDraft $draft
     * @param (callable(TResponse):array)|null $transform
     * @return TResponse
     */
    protected function runOperation(LogDraft $draft, callable $execute, ?callable $transform = null)
    {
        try {
            $response = $execute();
            $data = $transform ? $transform($response) : $response;
            $draft->finishSuccess($data);
            return $response;
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $draft->persist();
        }
    }

    protected function runStreaming(LogDraft $draft, Generator $generator, ?Closure $onDelta = null, bool $captureChunks = true): Generator
    {
        try {
            foreach ($generator as $delta) {
                $captureChunks ? $draft->accumulateChunk($delta) : $draft->appendToFinal($delta);
                if ($onDelta) { $onDelta($delta); }
                yield $delta;
            }
            $draft->finishSuccessStreaming();
        } catch (\Throwable $e) {
            $draft->finishError($e);
            throw $e;
        } finally {
            $draft->persist();
        }
    }
}
