<?php

namespace Iserter\UniformedAI\Services\Video\Providers;

use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * ElevenLabs Video driver — gateway to Sora 2, Google Veo, Kling, Wan, Seedance, etc.
 * API: https://elevenlabs.io/docs/overview/capabilities/image-video
 * Uses async pattern: POST to create generation, then poll until completed/failed.
 */
class ElevenLabsVideoDriver implements VideoContract
{
    private const CREATE_ENDPOINT = 'v1/image-video/generations';
    private const POLL_ENDPOINT = 'v1/image-video/generations';

    public function __construct(private array $cfg) {}

    public function generate(VideoRequest $request): VideoResponse
    {
        $options = $request->options ?? [];
        $http = $this->makeHttpClient();
        $model = $request->model ?? ($this->cfg['video_model'] ?? 'google-veo-3-fast');
        $payload = [
            'type' => 'video',
            'model_id' => $model,
            'prompt' => $request->prompt,
            'duration_seconds' => $request->durationSeconds ?? 4,
            'aspect_ratio' => $options['aspect_ratio'] ?? '16:9',
            'resolution' => $options['resolution'] ?? '1080p',
        ];
        if (! empty($options['start_frame_url'] ?? null)) {
            $payload['start_frame_url'] = $options['start_frame_url'];
        }
        if (! empty($options['end_frame_url'] ?? null)) {
            $payload['end_frame_url'] = $options['end_frame_url'];
        }
        if (! empty($options['reference_image_urls'] ?? [])) {
            $payload['reference_image_urls'] = $options['reference_image_urls'];
        }
        if (array_key_exists('negative_prompt', $options)) {
            $payload['negative_prompt'] = $options['negative_prompt'];
        }
        if (array_key_exists('enable_audio', $options)) {
            $payload['enable_audio'] = (bool) $options['enable_audio'];
        }

        $res = $http->post(self::CREATE_ENDPOINT, $payload);
        if (! $res->successful()) {
            throw new ProviderException(
                'ElevenLabs video generate failed',
                'elevenlabs',
                $res->status(),
                $res->json() ?? ['body' => $res->body()],
            );
        }

        $generationId = $res->json('id') ?? $res->json('generation_id');
        if (! $generationId) {
            throw new ProviderException(
                'ElevenLabs video generate missing generation id',
                'elevenlabs',
                $res->status(),
                $res->json(),
            );
        }

        $final = $this->pollUntilComplete($http, $generationId, $options['poll'] ?? []);
        $url = $this->extractVideoUrl($final);
        if ($url === null || $url === '') {
            throw new ProviderException(
                'ElevenLabs video generation returned no output URL',
                'elevenlabs',
                502,
                $final,
            );
        }
        return new VideoResponse(b64Video: null, url: $url, format: 'mp4', raw: $final);
    }

    public function edit(VideoRequest $request): VideoResponse
    {
        return $this->generate($request);
    }

    private function makeHttpClient(): PendingRequest
    {
        return HttpClientFactory::make($this->cfg, 'elevenlabs')
            ->withHeaders(['xi-api-key' => $this->cfg['api_key'] ?? '']);
    }

    private function extractVideoUrl(array $final): ?string
    {
        $outputs = $final['outputs'] ?? $final['data']['outputs'] ?? [];
        $first = is_array($outputs) ? ($outputs[0] ?? null) : null;
        if (is_array($first)) {
            return $first['url'] ?? $first['output_url'] ?? null;
        }
        return $final['output_url'] ?? $final['url'] ?? $final['data']['output_url'] ?? null;
    }

    private function pollUntilComplete(PendingRequest $http, string $generationId, array $pollOptions): array
    {
        $interval = (int) ($pollOptions['interval'] ?? 5);
        $timeout = (int) ($pollOptions['timeout'] ?? 900);
        $url = self::POLL_ENDPOINT.'/'.$generationId;
        $started = time();

        do {
            $res = $http->get($url);
            if (! $res->successful()) {
                throw new ProviderException(
                    'ElevenLabs video generation status failed',
                    'elevenlabs',
                    $res->status(),
                    $res->json() ?? ['body' => $res->body()],
                );
            }
            $json = $res->json() ?? [];
            $status = $json['status'] ?? $json['data']['status'] ?? null;

            if ($status === 'completed' || $status === 'succeeded') {
                return $json;
            }
            if ($status === 'failed' || $status === 'error') {
                throw new ProviderException(
                    'ElevenLabs video generation failed',
                    'elevenlabs',
                    $res->status(),
                    $json,
                );
            }

            if ((time() - $started) > $timeout) {
                throw new ProviderException(
                    'ElevenLabs video generation timeout',
                    'elevenlabs',
                    504,
                    $json,
                );
            }
            sleep($interval);
        } while (true);
    }
}
