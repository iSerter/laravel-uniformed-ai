<?php

use Illuminate\Support\Facades\Http;
use Iserter\UniformedAI\Services\Image\DTOs\ImageRequest;
use Iserter\UniformedAI\Facades\AI;

it('generates mj image via KIE driver with polling', function() {
    config()->set('uniformed-ai.defaults.image', 'kie');
    config()->set('uniformed-ai.providers.kie.api_key', 'test-key');
    config()->set('uniformed-ai.providers.kie.base_url', 'https://api.kie.ai');

    $taskId = 'mj_task_123';

    Http::fake([
        // initial generate
        'https://api.kie.ai/api/v1/mj/generate' => Http::response(['code'=>200,'data'=>['taskId'=>$taskId]], 200),
        // first poll (in progress)
        'https://api.kie.ai/api/v1/mj/record-info*' => Http::sequence()
            ->push(['code'=>200,'data'=>['taskId'=>$taskId,'successFlag'=>0]], 200)
            ->push(['code'=>200,'data'=>[
                'taskId'=>$taskId,
                'successFlag'=>1,
                'resultInfoJson'=>[
                    'resultUrls'=>[
                        ['resultUrl'=>'https://example.com/img1.jpg'],
                        ['resultUrl'=>'https://example.com/img2.jpg'],
                    ]
                ]
            ]],200),
    ]);

    // shorten poll interval to 0 for test speed
    $req = new ImageRequest(prompt: 'A test prompt', model: 'mj', options: ['poll'=>['interval'=>0]]);
    $resp = AI::image()->create($req);

    expect($resp->images)->toHaveCount(2);
    expect($resp->images[0]['url'])->toBe('https://example.com/img1.jpg');
});

it('generates 4o image via KIE driver', function() {
    config()->set('uniformed-ai.defaults.image', 'kie');
    config()->set('uniformed-ai.providers.kie.api_key', 'test-key');
    config()->set('uniformed-ai.providers.kie.base_url', 'https://api.kie.ai');

    $taskId = 'task_4o_abc';

    Http::fake([
        'https://api.kie.ai/api/v1/gpt4o-image/generate' => Http::response(['code'=>200,'data'=>['taskId'=>$taskId]], 200),
        'https://api.kie.ai/api/v1/gpt4o-image/record-info*' => Http::response([
            'code'=>200,
            'data'=>[
                'taskId'=>$taskId,
                'successFlag'=>1,
                'response'=>[ 'result_urls'=>['https://example.com/generated.png'] ]
            ]
        ], 200),
    ]);

    $req = new ImageRequest(prompt: 'A 4o cat', model: '4o', size: '1:1', options: ['poll'=>['interval'=>0]]);
    $resp = AI::image()->create($req);

    expect($resp->images)->toHaveCount(1);
    expect($resp->images[0]['url'])->toBe('https://example.com/generated.png');
});

it('upscales an mj image via KIE driver', function() {
    config()->set('uniformed-ai.defaults.image', 'kie');
    config()->set('uniformed-ai.providers.kie.api_key', 'test-key');
    config()->set('uniformed-ai.providers.kie.base_url', 'https://api.kie.ai');

    $origTask = 'mj_task_orig';
    $upscaleTask = 'mj_task_upscale';

    Http::fake([
        'https://api.kie.ai/api/v1/mj/upscale' => Http::response(['code'=>200,'data'=>['taskId'=>$upscaleTask]], 200),
        'https://api.kie.ai/api/v1/mj/record-info*' => Http::response([
            'code'=>200,
            'data'=>[
                'taskId'=>$upscaleTask,
                'successFlag'=>1,
                'resultInfoJson'=>[
                    'resultUrls'=>[
                        ['resultUrl'=>'https://example.com/upscaled.jpg']
                    ]
                ]
            ]
        ], 200),
    ]);

        $req = new ImageRequest(prompt: 'ignored for upscale', model: 'mj', options: ['taskId'=>$origTask, 'index'=>2, 'poll'=>['interval'=>0]]);
        $resp = AI::image()->upscale($req);

    expect($resp->images)->toHaveCount(1);
    expect($resp->images[0]['url'])->toBe('https://example.com/upscaled.jpg');
});
