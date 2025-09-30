<?php

namespace Iserter\UniformedAI\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceUsageLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'stream_chunks' => 'array',
        'extra' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('uniformed-ai.logging.table', parent::getTable());
    }
}
