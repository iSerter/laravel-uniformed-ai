<?php

use Iserter\UniformedAI\Integration\Concerns\TracksUsage;
use Iserter\UniformedAI\Models\ServicePricing;
use Iserter\UniformedAI\Models\ServiceUsageLog;

beforeEach(function () {
    config()->set('uniformed-ai.logging.enabled', true);
    config()->set('uniformed-ai.logging.queue.enabled', false);
});

function makeAgentWithTracksUsage(string $provider = 'unknown', string $model = 'unknown'): object
{
    return new class($provider, $model) {
        use TracksUsage;

        public function __construct(
            private string $testProvider,
            private string $testModel,
        ) {}

        protected function usageProvider(): string { return $this->testProvider; }
        protected function usageModel(): string { return $this->testModel; }
    };
}

// --- ExtractsUsage duck-typing tests ---

it('extracts usage from object with promptTokens/completionTokens', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new stdClass();
    $response->usage = new stdClass();
    $response->usage->promptTokens = 100;
    $response->usage->completionTokens = 50;
    $response->text = 'Hello';

    $result = $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->extra['usage']['prompt_tokens'])->toBe(100);
    expect($log->extra['usage']['completion_tokens'])->toBe(50);
});

it('extracts usage from object with inputTokens/outputTokens', function () {
    $agent = makeAgentWithTracksUsage('anthropic', 'claude-sonnet-4-5-20250514');

    $response = new stdClass();
    $response->usage = new stdClass();
    $response->usage->inputTokens = 200;
    $response->usage->outputTokens = 75;

    $result = $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->extra['usage']['prompt_tokens'])->toBe(200);
    expect($log->extra['usage']['completion_tokens'])->toBe(75);
});

it('extracts usage from array-style usage', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new stdClass();
    $response->usage = ['prompt_tokens' => 30, 'completion_tokens' => 10];

    $result = $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->extra['usage']['prompt_tokens'])->toBe(30);
    expect($log->extra['usage']['completion_tokens'])->toBe(10);
});

it('extracts usage from usage() method', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new class {
        public string $text = 'Hi';
        public function usage(): object {
            $u = new stdClass();
            $u->promptTokens = 42;
            $u->completionTokens = 18;
            return $u;
        }
    };

    $result = $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->extra['usage']['prompt_tokens'])->toBe(42);
    expect($log->extra['usage']['completion_tokens'])->toBe(18);
});

it('returns null usage for objects without usage data', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new stdClass();
    $response->text = 'Hello';

    $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->status)->toBe('success');
    expect($log->extra)->toBeNull();
});

// --- TracksUsage wrapper tests ---

it('records tracked prompt with usage and cost', function () {
    ServicePricing::create([
        'provider' => 'openai', 'service_type' => 'chat', 'model_pattern' => 'gpt-4.1',
        'unit' => '1M_tokens', 'input_cost_cents' => 250, 'output_cost_cents' => 1000,
        'currency' => 'USD', 'active' => true,
    ]);

    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new stdClass();
    $response->text = 'Great pitch!';
    $response->usage = new stdClass();
    $response->usage->promptTokens = 1000;
    $response->usage->completionTokens = 500;

    $result = $agent->trackedPrompt('Improve my pitch', fn () => $response);

    expect($result)->toBe($response);

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('openai');
    expect($log->model)->toBe('gpt-4.1');
    expect($log->service_type)->toBe('chat');
    expect($log->service_operation)->toBe('prompt');
    expect($log->status)->toBe('success');
    expect($log->extra['usage']['prompt_tokens'])->toBe(1000);
    expect($log->extra['usage']['completion_tokens'])->toBe(500);
    expect($log->extra['usage']['pricing_source'])->toContain('db:');
});

it('records tracked stream', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $chunks = [];
    foreach ($agent->trackedStream('Hello', fn () => (function () {
        yield 'Hello';
        yield ' world';
    })()) as $chunk) {
        $chunks[] = $chunk;
    }

    expect($chunks)->toBe(['Hello', ' world']);

    $log = ServiceUsageLog::first();
    expect($log->status)->toBe('success');
    expect($log->service_operation)->toBe('stream');
});

it('resolves provider from enum-like object', function () {
    $agent = new class {
        use TracksUsage;

        public object $provider;

        public function __construct()
        {
            $this->provider = new class {
                public string $value = 'Anthropic';
            };
        }
    };

    $response = new stdClass();
    $response->text = 'Hi';

    $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('anthropic');
});

it('uses overridden usageProvider and usageModel', function () {
    $agent = new class {
        use TracksUsage;

        protected function usageProvider(): string { return 'google'; }
        protected function usageModel(): string { return 'gemini-2.5-pro'; }
    };

    $response = new stdClass();
    $agent->trackedPrompt('Hi', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->provider)->toBe('google');
    expect($log->model)->toBe('gemini-2.5-pro');
});

it('extracts response text for logging', function () {
    $agent = makeAgentWithTracksUsage('openai', 'gpt-4.1');

    $response = new stdClass();
    $response->text = 'The answer is 42';

    $agent->trackedPrompt('What is the meaning?', fn () => $response);

    $log = ServiceUsageLog::first();
    expect($log->response_payload)->toBe(['text' => 'The answer is 42']);
});
