<?php

use Illuminate\Support\Arr;
use Illuminate\Filesystem\Filesystem;

it('service pricing JSON is valid and structured', function () {
    $dataDir = __DIR__ . '/../../database/data';
    $files = glob($dataDir . '/*.json');
    expect($files)->not->toBeEmpty();

    foreach ($files as $path) {
        $raw = file_get_contents($path);
        expect($raw)->not->toBe('');

        $decoded = json_decode($raw, true);
        expect(json_last_error())->toBe(JSON_ERROR_NONE, "JSON syntax error in file: " . basename($path));
        expect($decoded)->toBeArray();
        expect($decoded)->not->toBeEmpty();

        foreach ($decoded as $i => $row) {
            expect($row)->toBeArray();
            // minimal required keys (provider & model_pattern) should exist
            expect($row)->toHaveKeys(['provider', 'model_pattern']);

            // provider
            expect($row['provider'])->toBeString()->not->toBe('');
            // model pattern
            expect($row['model_pattern'])->toBeString()->not->toBe('');
            // unit
            expect($row['unit'] ?? '1K_tokens')->toBeString();
            // costs (nullable allowed)
            if (array_key_exists('input_cost_cents', $row) && $row['input_cost_cents'] !== null) {
                expect($row['input_cost_cents'])->toBeInt()->toBeGreaterThanOrEqual(0);
            }
            if (array_key_exists('output_cost_cents', $row) && $row['output_cost_cents'] !== null) {
                expect($row['output_cost_cents'])->toBeInt()->toBeGreaterThanOrEqual(0);
            }
            // currency
            expect(($row['currency'] ?? 'USD'))->toBeString()->toHaveLength(3);
            // active flag
            expect($row['active'] ?? true)->toBeBool();
            // meta JSON-compatible
            if (isset($row['meta'])) {
                expect(is_array($row['meta']) || is_object($row['meta']))->toBeTrue();
            }
        }
    }
});
