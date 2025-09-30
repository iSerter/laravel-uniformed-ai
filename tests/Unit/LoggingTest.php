<?php

use Illuminate\Support\Facades\Bus;
use Iserter\UniformedAI\Logging\{LogDraft, LoggingDriverFactory, PersistServiceUsageLogJob};
use Iserter\UniformedAI\Logging\Usage\{PricingEngine, ProviderUsageExtractor, HeuristicCl100kEstimator, UsageMetricsCollector};
use Iserter\UniformedAI\Support\PricingRepository;
use Iserter\UniformedAI\Models\ServiceUsageLog;

class DummyChatDriver implements Iserter\UniformedAI\Services\Chat\Contracts\ChatContract {
    public function __construct(public $responses = []){}
    public function send(Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest $request): Iserter\UniformedAI\Services\Chat\DTOs\ChatResponse {
        return new Iserter\UniformedAI\Services\Chat\DTOs\ChatResponse('ok');
    }
    public function stream(Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest $request, ?Closure $onDelta = null): Generator {
        yield 'A'; yield 'B';
    }
}

it('sanitizes secrets and truncates large payloads', function() {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.truncate.request_chars', 120); // ensure we keep structure
    $draft = LogDraft::start('chat','openai','send', [
        'api_key' => 'sk-THISSHOULDREDact12345678901234567890',
        'nested' => ['token' => 'ABCDEF1234567890ABCDEF1234567890'],
        'big' => str_repeat('X', 300),
    ], 'gpt-test');
    $draft->finishSuccess(['content' => 'Hi']);
    $payload = $draft->payload();
    // structure may be truncated; assert redaction occurred anywhere in json and secret not present
    $json = json_encode($payload['request_payload']);
    expect($json)->toContain('***REDACTED***');
    expect($json)->not->toContain('THISSHOULDREDact');
});

it('persists synchronously when queue disabled', function() {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.queue.enabled', false);
    $draft = LogDraft::start('chat','openai','send', ['x'=>'y']);
    $draft->finishSuccess(['content'=>'hi']);
    $draft->persist();
    expect(ServiceUsageLog::count())->toBe(1);
});

it('dispatches job when queue enabled', function() {
    Bus::fake();
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.queue.enabled', true);
    $draft = LogDraft::start('chat','openai','send', ['x'=>'y']);
    $draft->finishSuccess(['content'=>'hi']);
    $draft->persist();
    Bus::assertDispatched(PersistServiceUsageLogJob::class);
});

it('wrap factory returns decorated chat driver', function() {
    config()->set('uniformed-ai.logging.enabled', true);
    $driver = new DummyChatDriver();
    $wrapped = LoggingDriverFactory::wrap('chat','openai',$driver);
    expect(get_class($wrapped))->toContain('LoggingChatDriver');
});

it('usage metrics collector attaches pricing + estimation', function() {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.usage.enabled', true);
    config()->set('uniformed-ai.logging.usage.services.chat', true);

    // Seed pricing row
    Iserter\UniformedAI\Models\ServicePricing::create([
        'provider' => 'openai','service_type'=>'chat','model_pattern'=>'gpt-price*','unit'=>'1K_tokens',
        'input_cost_cents'=>10,'output_cost_cents'=>20,'currency'=>'USD','active'=>true,
    ]);

    $collector = new UsageMetricsCollector(new ProviderUsageExtractor(), new HeuristicCl100kEstimator(), new PricingEngine(new PricingRepository()));

    $req = new Iserter\UniformedAI\Services\Chat\DTOs\ChatRequest([
        new Iserter\UniformedAI\Services\Chat\DTOs\ChatMessage('user','Hello world')
    ], model: 'gpt-price-mini');

    $metrics = $collector->collectChat('openai', 'gpt-price-mini', $req, null, 'Hi', 'send');
    expect($metrics)->not->toBeNull();
    $arr = $metrics->toArray();
    expect($arr['confidence'])->toBe('estimated');
    expect($arr['pricing_source'])->toContain('db:');
});
