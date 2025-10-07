<?php

namespace Iserter\UniformedAI\Services\Audio\Providers;

use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\ServiceCatalog;

/**
 * Replicate audio (text -> speech) driver.
 * Strategy: create a prediction for a speech model and wait synchronously (Prefer: wait header) if supported.
 * For now we treat model identifiers as full replicate slugs from ServiceCatalog or request override.
 */
class ReplicateAudioDriver implements AudioContract
{
    public function __construct(private array $cfg) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'replicate');

        $model = $request->model ?? ($this->cfg['model'] ?? ServiceCatalog::models('audio','replicate')[0] ?? null);
        if (!$model) {
            throw new ProviderException('Replicate audio model not configured', 'replicate', 0, []);
        }

        // Replicate prediction create expects version or model+version slug. We'll allow caller to supply either full slug or alias.
        // If user supplies a simple model without version hash, pass as is (Replicate may reject; future enhancement: model lookup).

        $payload = [
            'version' => $model,
            'input' => [
                // Heuristic: common input key names used by TTS models on Replicate; many expect 'text'
                'text' => $request->text,
                // Optional mapping for voice if supported by the chosen model
                'voice' => $request->voice,
                // Additional options forwarded verbatim if provided
            ],
        ];

        if (!empty($request->options)) {
            // merge without overwriting text
            $payload['input'] = array_merge($request->options, $payload['input']);
        }

        $res = $http->withHeaders(['Prefer' => 'wait'])
            ->post('predictions', $payload);

        if (!$res->successful()) {
            throw new ProviderException('Replicate audio prediction failed', 'replicate', $res->status(), $res->json());
        }

        $json = $res->json();
        if (!isset($json['status'])) {
            throw new ProviderException('Malformed replicate response', 'replicate', $res->status(), $json);
        }

        if ($json['status'] !== 'succeeded') {
            // Best-effort error propagation
            throw new ProviderException('Replicate prediction not succeeded: '.$json['status'], 'replicate', $res->status(), $json);
        }

        // Output may be an array of URLs or a single URL/string depending on model.
        $output = $json['output'] ?? null;
        if (!$output) {
            throw new ProviderException('Replicate audio output missing', 'replicate', $res->status(), $json);
        }

        // Normalize to first element if array
        $audioUrl = is_array($output) ? ($output[0] ?? null) : $output;
        if (!is_string($audioUrl)) {
            throw new ProviderException('Unexpected replicate audio output format', 'replicate', $res->status(), $json);
        }

        // We need to fetch the binary audio file (requires auth header again)
        $audioRes = $http->get($audioUrl);
        if (!$audioRes->successful()) {
            throw new ProviderException('Failed downloading replicate audio asset', 'replicate', $audioRes->status(), $audioRes->json() ?? ['body' => $audioRes->body()]);
        }

        $b64 = base64_encode($audioRes->body());
        return new AudioResponse($b64, [
            'model' => $model,
            'prediction_id' => $json['id'] ?? null,
            'status' => $json['status'],
            'raw_prediction' => [
                'id' => $json['id'] ?? null,
                'metrics' => $json['metrics'] ?? null,
            ],
        ]);
    }

    public function getAvailableVoices(bool $refresh = false): array
    {
        // Replicate does not offer a unified voice list across all speech models.
        // We return an empty map with note. Future enhancement: inspect model README & metadata.
        return ['map' => [], '_raw' => ['note' => 'Replicate voice listing not implemented']];
    }
}
