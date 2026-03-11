<?php

namespace Iserter\UniformedAI\Services\Video\Providers;

use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * Google (Gemini Veo) video driver.
 * Uses Gemini API v1beta long-running predict: POST predictLongRunning, then poll until done.
 * Returned video URL may require x-goog-api-key when downloading (same key as request).
 *
 * @see https://ai.google.dev/gemini-api/docs/video
 */
class GoogleVideoDriver implements VideoContract
{
    private const BASE_PATH = 'v1beta';

    public function __construct(private array $cfg) {}

    public function generate(VideoRequest $request): VideoResponse
    {
        $options = $request->options ?? [];
        $http = $this->makeHttpClient();
        $model = $request->model
            ?? $this->cfg['video_model']
            ?? ($this->cfg['video']['model'] ?? null)
            ?? 'veo-3.1-generate-preview';

        $body = [
            'instances' => $this->buildInstances($request),
            'parameters' => $this->buildParameters(
                $request->durationSeconds,
                $options,
            ),
        ];

        $endpoint = self::BASE_PATH.'/models/'.$model.':predictLongRunning';
        $res = $http->post($endpoint, $body);

        if (! $res->successful()) {
            $raw = $res->json() ?? ['body' => $res->body()];
            $detail = $raw['error']['message'] ?? $raw['error']['status'] ?? json_encode($raw);
            throw new ProviderException(
                "Google Veo generate failed [{$res->status()}]: {$detail}",
                'google',
                $res->status(),
                $raw,
            );
        }

        $json = $res->json() ?? [];
        $operationName = $json['name'] ?? null;
        if (! $operationName || ! is_string($operationName)) {
            throw new ProviderException(
                'Google Veo generate missing operation name',
                'google',
                $res->status(),
                $json,
            );
        }

        $pollOptions = $options['poll'] ?? [];
        $final = $this->pollUntilDone($http, $operationName, $pollOptions);

        $uri = $this->extractVideoUri($final);
        if ($uri === null || $uri === '') {
            throw new ProviderException(
                'Google Veo generation returned no video URI',
                'google',
                502,
                $final,
            );
        }

        return new VideoResponse(b64Video: null, url: $uri, format: 'mp4', raw: $final);
    }

    public function edit(VideoRequest $request): VideoResponse
    {
        return $this->generate($request);
    }

    private function makeHttpClient(): PendingRequest
    {
        $client = HttpClientFactory::make($this->cfg, 'google');
        $key = $this->cfg['api_key'] ?? '';

        return $client->withHeaders(['x-goog-api-key' => $key]);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildInstances(VideoRequest $request): array
    {
        $options = $request->options ?? [];
        $instance = ['prompt' => $request->prompt];

        $imageData = $options['image_inline_data'] ?? null;
        if (is_array($imageData) && ! empty($imageData['data'])) {
            $instance['image'] = [
                'inlineData' => [
                    'mimeType' => $imageData['mimeType'] ?? 'image/png',
                    'data' => $imageData['data'],
                ],
            ];
        }

        $videoData = $options['video_inline_data'] ?? null;
        if (is_array($videoData) && ! empty($videoData['data'])) {
            $instance['video'] = [
                'inlineData' => [
                    'mimeType' => $videoData['mimeType'] ?? 'video/mp4',
                    'data' => $videoData['data'],
                ],
            ];
        }

        return [$instance];
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildParameters(?int $requestDurationSeconds, array $options): array
    {
        $params = [];

        $duration = $options['duration_seconds'] ?? $requestDurationSeconds;
        if ($duration !== null) {
            $params['durationSeconds'] = (string) (int) $duration;
        }

        if (isset($options['aspect_ratio'])) {
            $params['aspectRatio'] = $options['aspect_ratio'];
        }
        if (isset($options['resolution'])) {
            $params['resolution'] = $options['resolution'];
        }
        if (array_key_exists('negative_prompt', $options)) {
            $params['negativePrompt'] = (string) $options['negative_prompt'];
        }
        if (isset($options['last_frame_inline_data']) && is_array($options['last_frame_inline_data'])) {
            $lf = $options['last_frame_inline_data'];
            if (! empty($lf['data'])) {
                $params['lastFrame'] = [
                    'inlineData' => [
                        'mimeType' => $lf['mimeType'] ?? 'image/png',
                        'data' => $lf['data'],
                    ],
                ];
            }
        }
        if (! empty($options['reference_images']) && is_array($options['reference_images'])) {
            $params['referenceImages'] = [];
            foreach (array_slice($options['reference_images'], 0, 3) as $ref) {
                if (is_array($ref) && ! empty($ref['image']['inlineData']['data'])) {
                    $params['referenceImages'][] = [
                        'image' => ['inlineData' => $ref['image']['inlineData']],
                        'referenceType' => $ref['referenceType'] ?? 'asset',
                    ];
                }
            }
        }
        if (array_key_exists('number_of_videos', $options)) {
            $params['numberOfVideos'] = (int) $options['number_of_videos'];
        }
        if (isset($options['person_generation'])) {
            $params['personGeneration'] = $options['person_generation'];
        }

        return $params;
    }

    /**
     * Poll operation until done. Returns final operation payload.
     *
     * @param  array{interval?: int, timeout?: int}  $pollOptions
     * @return array<string, mixed>
     */
    private function pollUntilDone(PendingRequest $http, string $operationName, array $pollOptions): array
    {
        $interval = (int) ($pollOptions['interval'] ?? 10);
        $timeout = (int) ($pollOptions['timeout'] ?? 900);
        $url = self::BASE_PATH.'/'.$operationName;
        $started = time();

        do {
            $res = $http->get($url);
            if (! $res->successful()) {
                throw new ProviderException(
                    'Google Veo operation status failed',
                    'google',
                    $res->status(),
                    $res->json() ?? ['body' => $res->body()],
                );
            }
            $json = $res->json() ?? [];

            if (! empty($json['done'])) {
                if (! empty($json['error'])) {
                    throw new ProviderException(
                        'Google Veo operation failed: '.($json['error']['message'] ?? json_encode($json['error'])),
                        'google',
                        $json['error']['code'] ?? 500,
                        $json,
                    );
                }
                return $json;
            }

            if ((time() - $started) > $timeout) {
                throw new ProviderException(
                    'Google Veo generation timeout',
                    'google',
                    504,
                    $json,
                );
            }
            sleep($interval);
        } while (true);
    }

    /**
     * Extract video URI from completed operation. Operation has response in 'response' when done.
     *
     * @param  array<string, mixed>  $response
     */
    private function extractVideoUri(array $response): ?string
    {
        $inner = $response['response'] ?? $response;
        $samples = $inner['generateVideoResponse']['generatedSamples'] ?? null;
        if (! is_array($samples) || empty($samples)) {
            return null;
        }
        $first = $samples[0] ?? null;
        if (! is_array($first)) {
            return null;
        }

        return $first['video']['uri'] ?? null;
    }
}
