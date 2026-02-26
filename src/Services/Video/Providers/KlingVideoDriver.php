<?php

namespace Iserter\UniformedAI\Services\Video\Providers;

use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * KlingAI video driver — official Kuaishou Kling API (api.klingai.com).
 *
 * Supports text-to-video and image-to-video via async task lifecycle:
 *   POST /v1/videos/text2video  (or image2video)
 *   GET  /v1/videos/text2video/{task_id}  (or image2video/{task_id})
 *
 * Authentication: HS256 JWT generated from AccessKey + SecretKey.
 * Falls back to plain Bearer token when only `api_key` is provided (gateway integrations).
 *
 * @see https://app.klingai.com/global/dev/document-api
 */
class KlingVideoDriver implements VideoContract
{
    private const TEXT2VIDEO_PATH = 'v1/videos/text2video';
    private const IMAGE2VIDEO_PATH = 'v1/videos/image2video';

    public function __construct(private array $cfg) {}

    public function generate(VideoRequest $request): VideoResponse
    {
        $options = $request->options ?? [];
        $http = $this->makeHttpClient();
        $isImageToVideo = ! empty($options['image']) || ! empty($options['image_url']);
        [$createPath, $pollBasePath] = $isImageToVideo
            ? [self::IMAGE2VIDEO_PATH, self::IMAGE2VIDEO_PATH]
            : [self::TEXT2VIDEO_PATH, self::TEXT2VIDEO_PATH];

        $body = $isImageToVideo
            ? $this->buildImage2VideoBody($request, $options)
            : $this->buildText2VideoBody($request, $options);

        $res = $http->post($createPath, $body);
        if (! $res->successful()) {
            throw new ProviderException(
                'Kling video generate failed',
                'kling',
                $res->status(),
                $res->json() ?? ['body' => $res->body()],
            );
        }

        $json = $res->json() ?? [];
        $taskId = $json['data']['task_id'] ?? $json['task_id'] ?? null;
        if (! $taskId || ! is_string($taskId)) {
            throw new ProviderException(
                'Kling video generate missing task_id',
                'kling',
                $res->status(),
                $json,
            );
        }

        $pollOptions = $options['poll'] ?? [];
        $final = $this->pollUntilComplete($http, $pollBasePath.'/'.$taskId, $pollOptions);

        $url = $this->extractVideoUrl($final);
        if ($url === null || $url === '') {
            throw new ProviderException(
                'Kling video generation returned no video URL',
                'kling',
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
        $client = HttpClientFactory::make($this->cfg, 'kling');

        // Official API: generate HS256 JWT from access_key + secret_key
        if (! empty($this->cfg['access_key']) && ! empty($this->cfg['secret_key'])) {
            $token = $this->generateJwt(
                (string) $this->cfg['access_key'],
                (string) $this->cfg['secret_key'],
            );
            return $client->withToken($token);
        }

        // Gateway fallback: plain Bearer from api_key
        if (! empty($this->cfg['api_key'])) {
            return $client->withToken((string) $this->cfg['api_key']);
        }

        return $client;
    }

    /**
     * Generate an HS256 JWT for KlingAI official API.
     * Payload: iss=accessKey, exp=now+1800, nbf=now-5.
     */
    private function generateJwt(string $accessKey, string $secretKey): string
    {
        $header = $this->base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $now = time();
        $payload = $this->base64url(json_encode([
            'iss' => $accessKey,
            'exp' => $now + 1800,
            'nbf' => $now - 5,
        ], JSON_THROW_ON_ERROR));
        $signature = $this->base64url(hash_hmac('sha256', $header.'.'.$payload, $secretKey, true));

        return $header.'.'.$payload.'.'.$signature;
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildText2VideoBody(VideoRequest $request, array $options): array
    {
        $model = $request->model
            ?? $this->cfg['video_model']
            ?? ($this->cfg['video']['model'] ?? null)
            ?? 'kling-v2-1';

        $body = [
            'model_name' => $model,
            'prompt' => $request->prompt,
        ];

        $duration = $options['duration'] ?? $request->durationSeconds ?? 5;
        $body['duration'] = (string) (int) $duration;

        if (isset($options['aspect_ratio'])) {
            $body['aspect_ratio'] = $options['aspect_ratio'];
        }
        if (isset($options['mode'])) {
            $body['mode'] = $options['mode'];
        }
        if (array_key_exists('negative_prompt', $options)) {
            $body['negative_prompt'] = (string) $options['negative_prompt'];
        }
        if (isset($options['cfg_scale'])) {
            $body['cfg_scale'] = (float) $options['cfg_scale'];
        }
        if (isset($options['camera_control'])) {
            $body['camera_control'] = $options['camera_control'];
        }
        if (isset($options['callback_url'])) {
            $body['callback_url'] = $options['callback_url'];
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function buildImage2VideoBody(VideoRequest $request, array $options): array
    {
        $model = $request->model
            ?? $this->cfg['video_model']
            ?? ($this->cfg['video']['model'] ?? null)
            ?? 'kling-v2-1';

        $body = [
            'model_name' => $model,
            'image' => $options['image'] ?? $options['image_url'],
            'prompt' => $request->prompt,
        ];

        $duration = $options['duration'] ?? $request->durationSeconds ?? 5;
        $body['duration'] = (string) (int) $duration;

        if (isset($options['aspect_ratio'])) {
            $body['aspect_ratio'] = $options['aspect_ratio'];
        }
        if (isset($options['mode'])) {
            $body['mode'] = $options['mode'];
        }
        if (array_key_exists('negative_prompt', $options)) {
            $body['negative_prompt'] = (string) $options['negative_prompt'];
        }
        if (isset($options['cfg_scale'])) {
            $body['cfg_scale'] = (float) $options['cfg_scale'];
        }
        if (isset($options['image_tail'])) {
            $body['image_tail'] = $options['image_tail'];
        }
        if (isset($options['callback_url'])) {
            $body['callback_url'] = $options['callback_url'];
        }

        return $body;
    }

    /**
     * Poll until task_status is 'succeed' or 'completed'.
     *
     * @param  array{interval?: int, timeout?: int}  $pollOptions
     * @return array<string, mixed>
     */
    private function pollUntilComplete(PendingRequest $http, string $pollUrl, array $pollOptions): array
    {
        $interval = (int) ($pollOptions['interval'] ?? 5);
        $timeout = (int) ($pollOptions['timeout'] ?? 900);
        $started = time();

        do {
            $res = $http->get($pollUrl);
            if (! $res->successful()) {
                throw new ProviderException(
                    'Kling video task status failed',
                    'kling',
                    $res->status(),
                    $res->json() ?? ['body' => $res->body()],
                );
            }
            $json = $res->json() ?? [];

            // Official API: data.task_status; gateway APIs: root status
            $status = $json['data']['task_status'] ?? $json['status'] ?? null;

            if ($status === 'succeed' || $status === 'completed') {
                return $json;
            }
            if ($status === 'failed' || $status === 'error') {
                $errMsg = $json['data']['task_status_msg']
                    ?? $json['message']
                    ?? json_encode($json);
                throw new ProviderException(
                    'Kling video generation failed: '.$errMsg,
                    'kling',
                    $json['code'] ?? 500,
                    $json,
                );
            }

            if ((time() - $started) > $timeout) {
                throw new ProviderException(
                    'Kling video generation timeout',
                    'kling',
                    504,
                    $json,
                );
            }
            sleep($interval);
        } while (true);
    }

    /**
     * Extract the first video URL from a completed task response.
     * Supports both official API (data.task_result.videos[0].url) and gateway (url / video.url).
     *
     * @param  array<string, mixed>  $response
     */
    private function extractVideoUrl(array $response): ?string
    {
        // Official KlingAI format
        $videos = $response['data']['task_result']['videos'] ?? null;
        if (is_array($videos) && ! empty($videos)) {
            $first = $videos[0] ?? null;
            if (is_array($first) && isset($first['url'])) {
                return $first['url'];
            }
        }

        // Gateway formats
        return $response['url']
            ?? ($response['video']['url'] ?? null)
            ?? ($response['data']['url'] ?? null)
            ?? null;
    }
}
