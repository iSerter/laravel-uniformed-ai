<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Music\DTOs\MusicRequest;
use Iserter\UniformedAI\Facades\AI;

it('composes music via KIE driver with polling', function() {
    config()->set('uniformed-ai.defaults.music', 'kie');
    config()->set('uniformed-ai.providers.kie.api_key', 'test-key');
    config()->set('uniformed-ai.providers.kie.base_url', 'https://api.kie.ai');

    $taskId = 'music_task_123';
    $audioUrl = 'https://cdn.example.com/audio1.mp3';

    Http::fake([
        'https://api.kie.ai/api/v1/generate' => Http::response(['code'=>200,'data'=>['taskId'=>$taskId]], 200),
        'https://api.kie.ai/api/v1/generate/record-info*' => Http::sequence()
            ->push(['code'=>200,'data'=>['taskId'=>$taskId,'status'=>'PENDING']], 200)
            ->push(['code'=>200,'data'=>[
                'taskId'=>$taskId,
                'status'=>'SUCCESS',
                'response'=>[
                    'sunoData'=>[
                        ['audioUrl'=>$audioUrl]
                    ]
                ]
            ]], 200),
        $audioUrl => Http::response('AUDIO_BYTES', 200),
    ]);

    $req = new MusicRequest(prompt: 'Epic orchestral score', model: 'V3_5', options: ['poll'=>['interval'=>0]]);
    $resp = AI::music()->compose($req);

    expect($resp->b64Audio)->toBe(base64_encode('AUDIO_BYTES'));
    expect($resp->raw['data']['status'])->toBe('SUCCESS');
});
