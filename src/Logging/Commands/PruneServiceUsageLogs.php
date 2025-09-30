<?php

namespace Iserter\UniformedAI\Logging\Commands;

use Illuminate\Console\Command;
use Iserter\UniformedAI\Models\ServiceUsageLog;

class PruneServiceUsageLogs extends Command
{
    protected $signature = 'ai-usage-logs:prune';
    protected $description = 'Prune AI service usage logs older than configured days';

    public function handle(): int
    {
        if (!config('uniformed-ai.logging.prune.enabled', true)) {
            $this->info('Pruning disabled.');
            return self::SUCCESS;
        }
        $days = (int) config('uniformed-ai.logging.prune.days', 30);
        $cutoff = now()->subDays($days);
        $model = new ServiceUsageLog();
        $conn = config('uniformed-ai.logging.connection');
        if ($conn) $model->setConnection($conn);
        $count = $model->newQuery()->where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$count} usage log rows older than {$days} days.");
        return self::SUCCESS;
    }
}
