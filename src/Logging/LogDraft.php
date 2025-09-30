<?php

namespace Iserter\UniformedAI\Logging;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Iserter\UniformedAI\Models\ServiceUsageLog;
use Throwable;

class LogDraft
{
    use SanitizesPayloads;

    protected array $data = [];
    protected array $streamChunks = [];
    protected string $finalStream = '';
    protected bool $finished = false;

    public static function start(string $service, string $provider, ?string $operation, array $request, ?string $model = null): self
    {
        $self = new self();
        $self->data = [
            'service_type' => $service,
            'service_operation' => $operation,
            'provider' => $provider,
            'model' => $model,
            'status' => 'pending',
            'started_at' => now(),
            'request_payload' => $self->sanitize($request, 'request'),
            'user_id' => Auth::id(),
        ];
        return $self;
    }

    public function accumulateChunk(string $delta): void
    {
        if ($this->finished) return;
        if (count($this->streamChunks) >= (int) config('uniformed-ai.logging.stream.max_chunks', 500)) return;
        $this->streamChunks[] = $this->truncateChunk($delta);
        $this->finalStream .= $delta;
    }

    public function appendToFinal(string $delta): void
    {
        if ($this->finished) return; $this->finalStream .= $delta; }

    public function finishSuccess(mixed $response): void
    {
        if ($this->finished) return; $this->finished = true;
        $this->data['status'] = 'success';
        $this->data['finished_at'] = now();
        $this->data['latency_ms'] = $this->latency();
        $this->data['response_payload'] = $this->sanitize($this->normalize($response), 'response');
    }

    public function finishError(Throwable $e): void
    {
        if ($this->finished) return; $this->finished = true;
        $this->data['status'] = 'error';
        $this->data['finished_at'] = now();
        $this->data['latency_ms'] = $this->latency();
        $this->data['error_message'] = $e->getMessage();
        $this->data['error_class'] = $e::class;
        $this->data['exception_code'] = $e->getCode();
    }

    public function finishSuccessStreaming(): void
    {
        if ($this->finished) return; $this->finished = true;
        $this->data['status'] = 'success';
        $this->data['finished_at'] = now();
        $this->data['latency_ms'] = $this->latency();
        $this->data['response_payload'] = $this->sanitize(['content' => $this->finalStream], 'response');
        if (config('uniformed-ai.logging.stream.store_chunks')) {
            $this->data['stream_chunks'] = $this->streamChunks;
        }
    }

    protected function latency(): int
    {
        return (int) (microtime(true) * 1000 - $this->data['started_at']->getTimestampMs());
    }

    public function persist(): void
    {
        if (!$this->finished) return; // ensure finish invoked
        if (!config('uniformed-ai.logging.enabled', true)) return;
        $payload = $this->payload();
        if (config('uniformed-ai.logging.queue.enabled')) {
            $job = new \Iserter\UniformedAI\Logging\PersistServiceUsageLogJob($payload);
            if ($c = config('uniformed-ai.logging.queue.connection')) { $job->onConnection($c); }
            $job->onQueue(config('uniformed-ai.logging.queue.queue'));
            Bus::dispatch($job);
            return;
        }
        $this->storeSync($payload);
    }

    public function payload(): array
    {
        return $this->data;
    }

    protected function storeSync(array $data): void
    {
        try {
            $conn = config('uniformed-ai.logging.connection');
            $model = new ServiceUsageLog();
            if ($conn) $model->setConnection($conn);
            $model->fill($data);
            $model->save();
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function normalize(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) return $value->toArray();
            return get_object_vars($value);
        }
        return ['value' => $value];
    }
}
