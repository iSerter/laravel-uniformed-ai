<?php

namespace Iserter\UniformedAI\Logging;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Iserter\UniformedAI\Models\ServiceUsageLog;
use Throwable;

class PersistServiceUsageLogJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public function __construct(public array $payload) {}

    public function handle(): void
    {
        try {
            $model = new ServiceUsageLog();
            $conn = config('uniformed-ai.logging.connection');
            if ($conn) $model->setConnection($conn);
            $model->fill($this->payload)->save();
        } catch (Throwable $e) {
            report($e); // swallow
        }
    }
}
