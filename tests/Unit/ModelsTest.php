<?php

use Iserter\UniformedAI\Models\{ServicePricing, ServiceUsageLog};

it('applies current scope correctly with time windows', function() {
    $past = now()->subDay(); $future = now()->addDay();
    ServicePricing::create([
        'provider' => 'openai','service_type'=>null,'model_pattern'=>'gpt-x','unit'=>'1K_tokens','currency'=>'USD','active'=>true,
        'effective_at' => $past, 'expires_at' => $future,
    ]);
    ServicePricing::create([
        'provider' => 'openai','service_type'=>null,'model_pattern'=>'gpt-old','unit'=>'1K_tokens','currency'=>'USD','active'=>true,
        'effective_at' => now()->subDays(10), 'expires_at' => now()->subDay(),
    ]);
    ServicePricing::create([
        'provider' => 'openai','service_type'=>null,'model_pattern'=>'gpt-inactive','unit'=>'1K_tokens','currency'=>'USD','active'=>false,
    ]);

    $rows = ServicePricing::current()->pluck('model_pattern')->all();
    expect($rows)->toContain('gpt-x');
    expect($rows)->not->toContain('gpt-old');
    expect($rows)->not->toContain('gpt-inactive');
});

it('service usage log casts arrays & dates', function() {
    $log = ServiceUsageLog::create([
        'service_type' => 'chat', 'provider' => 'openai', 'status' => 'success',
        'started_at' => now(), 'finished_at' => now(), 'request_payload' => ['k'=>'v'], 'response_payload'=>['x'=>1],
    ]);
    expect($log->request_payload)->toBeArray();
    expect($log->finished_at)->toBeInstanceOf(\Carbon\Carbon::class);
});
