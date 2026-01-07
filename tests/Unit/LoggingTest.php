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

it('pricing engine correctly calculates costs for 1M_tokens unit', function() {
    Iserter\UniformedAI\Models\ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-test-1m',
        'unit' => '1M_tokens', 'input_cost_cents' => 250, 'output_cost_cents' => 2000,
        'currency' => 'USD', 'active' => true,
    ]);

    $engine = new PricingEngine(new PricingRepository());

    // With 1M_tokens unit: 1000 tokens = 1/1000 of unit = 0.25 input cents, 2 output cents
    $result = $engine->price('openai', 'gpt-test-1m', 'chat', 1000, 1000);
    expect($result)->not->toBeNull();
    expect($result['pricing_source'])->toContain('db:');

    // With larger token counts for clearer cost calculation
    // 1,000,000 tokens input = 250 cents, 1,000,000 tokens output = 2000 cents
    $result = $engine->price('openai', 'gpt-test-1m', 'chat', 1_000_000, 1_000_000);
    expect($result['input_cost_cents'])->toBe(250);
    expect($result['output_cost_cents'])->toBe(2000);
    expect($result['total_cost_cents'])->toBe(2250);

    // Verify correct divisor: 2933 prompt, 1701 completion
    // input: (2933/1_000_000) * 250 = 0.73325 => rounds to 1 (banker's rounding)
    // output: (1701/1_000_000) * 2000 = 3.402 => rounds to 3
    $result = $engine->price('openai', 'gpt-test-1m', 'chat', 2933, 1701);
    expect($result['input_cost_cents'])->toBe(1);
    expect($result['output_cost_cents'])->toBe(3);
    expect($result['total_cost_cents'])->toBe(4);
});

it('pricing engine correctly calculates costs for 1K_tokens unit', function() {
    Iserter\UniformedAI\Models\ServicePricing::create([
        'provider' => 'testprov', 'service_type' => 'chat', 'model_pattern' => 'gpt-test-1k',
        'unit' => '1K_tokens', 'input_cost_cents' => 10, 'output_cost_cents' => 20,
        'currency' => 'USD', 'active' => true,
    ]);

    $engine = new PricingEngine(new PricingRepository());

    // With 1K_tokens unit: 1000 tokens = 1 unit = 10 input cents, 20 output cents
    $result = $engine->price('testprov', 'gpt-test-1k', 'chat', 1000, 1000);
    expect($result['input_cost_cents'])->toBe(10);
    expect($result['output_cost_cents'])->toBe(20);
    expect($result['total_cost_cents'])->toBe(30);
});
