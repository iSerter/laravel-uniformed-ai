<?php

namespace Iserter\UniformedAI\Services\Music\Providers;

use Iserter\UniformedAI\Services\Music\Contracts\MusicContract;
use Iserter\UniformedAI\Services\Music\DTOs\{MusicRequest, MusicResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;

/**
 * KIE (Suno style) Music driver.
 * Flow:
 *  1. POST /api/v1/generate (or other endpoints later) => taskId
 *  2. Poll /api/v1/generate/record-info?taskId=... until status is terminal
 *     SUCCESS | FIRST_SUCCESS | TEXT_SUCCESS (we may choose to return early on FIRST_SUCCESS?)
 * For MVP we wait for SUCCESS or FIRST_SUCCESS (if timeout imminent) and extract first audio URL.
 */
class KIEMusicDriver implements MusicContract
{
    public function __construct(private array $cfg) {}

    public function compose(MusicRequest $request): MusicResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'kie');
        $endpointGenerate = 'api/v1/generate';
        $endpointStatus   = 'api/v1/generate/record-info';

        $payload = $this->buildGeneratePayload($request);
        $res = $http->post($endpointGenerate, $payload);
        if (!$res->successful()) {
            throw new ProviderException('KIE music generate error', 'kie', $res->status(), $res->json());
        }
        $json = $res->json();
        $taskId = $json['data']['taskId'] ?? null;
        if (!$taskId) {
            throw new ProviderException('KIE music generate missing taskId', 'kie', $res->status(), $json);
        }

        $final = $this->pollUntilComplete($http, $endpointStatus, $taskId, $request->options['poll'] ?? []);
        $audioB64 = $this->extractAudioBase64($final, $request->format);

        return new MusicResponse($audioB64, $final);
    }

    private function buildGeneratePayload(MusicRequest $r): array
    {
        $payload = [
            'prompt' => $r->prompt,
        ];
        if (!empty($r->model)) $payload['model'] = $r->model; // e.g. V3_5, V4, V4_5

        // Interpret known options drawn from docs (safe pass-through)
        $opt = $r->options ?? [];
        $mapKeys = [
            'customMode', 'instrumental', 'style', 'title', 'callBackUrl',
            // future expansion keys
        ];
        foreach ($mapKeys as $k) {
            if (array_key_exists($k, $opt)) $payload[$k] = $opt[$k];
        }

        return $payload;
    }

    /**
     * Poll status endpoint with exponential backoff-ish (simple capping) until terminal state.
     * Terminal statuses: SUCCESS, FIRST_SUCCESS, TEXT_SUCCESS, CREATE_TASK_FAILED, GENERATE_AUDIO_FAILED, SENSITIVE_WORD_ERROR, CALLBACK_EXCEPTION
     */
    private function pollUntilComplete($http, string $statusEndpoint, string $taskId, array $pollOptions): array
    {
        $sleepSeconds = (int)($pollOptions['interval'] ?? 10);
        $timeoutSeconds = (int)($pollOptions['timeout'] ?? 600); // 10 min
        $started = time();
        $url = $statusEndpoint.'?taskId='.urlencode($taskId);
        do {
            $res = $http->get($url);
            if (!$res->successful()) {
                throw new ProviderException('KIE music status error', 'kie', $res->status(), $res->json());
            }
            $json = $res->json();
            $status = $json['data']['status'] ?? null;
            if ($this->isTerminalStatus($status)) {
                return $json;
            }
            if ((time() - $started) > $timeoutSeconds) {
                throw new ProviderException('KIE music generation timeout', 'kie', 504, $json);
            }
            sleep($sleepSeconds);
        } while (true);
    }

    private function isTerminalStatus(?string $status): bool
    {
        if ($status === null) return false;
        return in_array($status, [
            'SUCCESS', 'FIRST_SUCCESS', 'TEXT_SUCCESS',
            'CREATE_TASK_FAILED', 'GENERATE_AUDIO_FAILED', 'SENSITIVE_WORD_ERROR', 'CALLBACK_EXCEPTION'
        ], true);
    }

    /**
     * Extract first audio asset, fetch bytes (if authorized) or return placeholder base64.
     * Provider response example:
     * data.response.sunoData[] => { audioUrl, ... }
     */
    private function extractAudioBase64(array $final, string $format): string
    {
        $data = $final['data'] ?? [];
        $sunoData = $data['response']['sunoData'] ?? [];
        $first = $sunoData[0] ?? [];
        $audioUrl = $first['audioUrl'] ?? $first['audio_url'] ?? null;
        if (!$audioUrl) {
            // Could be a failure case returning error statuses
            return base64_encode('');
        }

        // Strategy: attempt direct GET if same host or accessible (non-auth maybe needs header). We reuse http client.
        try {
            $http = HttpClientFactory::make($this->cfg, 'kie');
            $audioRes = $http->get($audioUrl);
            if ($audioRes->successful()) {
                return base64_encode($audioRes->body());
            }
        } catch (\Throwable $e) {
            // swallow; fallback to URL embedding
        }

        // Fallback: encode JSON with URL so caller can fetch later
        return base64_encode(json_encode(['url' => $audioUrl, 'format' => $format]));
    }
}
