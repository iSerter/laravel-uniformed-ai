<?php

use Iserter\UniformedAI\Logging\SanitizesPayloads;

// Create a test class that uses the trait
class SanitizesTester
{
    use SanitizesPayloads;

    public function testSanitize(array $data, string $type = 'request'): array
    {
        return $this->sanitize($data, $type);
    }
}

it('preserves JSON structure when truncating large payloads', function () {
    config()->set('uniformed-ai.logging.truncate.request_chars', 200);
    config()->set('uniformed-ai.logging.redaction.mask', '***REDACTED***');

    $tester = new SanitizesTester();
    $result = $tester->testSanitize([
        'model' => 'gpt-4.1-mini',
        'messages' => [
            ['role' => 'user', 'content' => str_repeat('A', 500)],
        ],
        'temperature' => 0.7,
    ]);

    // Structure must be preserved — must still be a valid nested array
    expect($result)->toBeArray();
    expect($result)->toHaveKey('model');
    expect($result)->toHaveKey('messages');
    expect($result)->toHaveKey('temperature');
    expect($result['model'])->toBe('gpt-4.1-mini');
    expect($result['temperature'])->toBe(0.7);

    // The long content should be truncated
    $content = $result['messages'][0]['content'];
    expect($content)->toContain('...(truncated)');

    // Total JSON size should be within limit
    $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    expect(strlen($json))->toBeLessThanOrEqual(200);
});

it('does not truncate small payloads', function () {
    config()->set('uniformed-ai.logging.truncate.request_chars', 20000);
    config()->set('uniformed-ai.logging.redaction.mask', '***REDACTED***');

    $tester = new SanitizesTester();
    $data = ['model' => 'gpt-4.1-mini', 'messages' => [['role' => 'user', 'content' => 'Hello']]];
    $result = $tester->testSanitize($data);

    expect($result['messages'][0]['content'])->toBe('Hello');
});

it('truncates longest strings first', function () {
    config()->set('uniformed-ai.logging.truncate.request_chars', 300);
    config()->set('uniformed-ai.logging.redaction.mask', '***REDACTED***');

    $tester = new SanitizesTester();
    $result = $tester->testSanitize([
        'short' => 'I am short',
        'long' => str_repeat('The quick brown fox jumps over the lazy dog. ', 10),
        'medium' => str_repeat('A medium sentence here. ', 5),
    ]);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('short');
    expect($result)->toHaveKey('long');
    expect($result['short'])->toBe('I am short'); // Short strings preserved

    // Long string should be truncated
    expect($result['long'])->toContain('...(truncated)');
});

it('still redacts secrets even when truncating', function () {
    config()->set('uniformed-ai.logging.truncate.request_chars', 500);
    config()->set('uniformed-ai.logging.redaction.mask', '***REDACTED***');

    $tester = new SanitizesTester();
    $result = $tester->testSanitize([
        'api_key' => 'sk-secret12345678901234567890',
        'data' => str_repeat('X', 600),
    ]);

    expect($result['api_key'])->toBe('***REDACTED***');
    $json = json_encode($result);
    expect($json)->not->toContain('sk-secret');
});
