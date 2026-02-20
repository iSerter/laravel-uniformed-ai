<?php

namespace Iserter\UniformedAI\Services\Image\Providers;

use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Illuminate\Http\Client\PendingRequest;

/**
 * ElevenLabs Image driver — gateway to Google Nano Banana, Seedream 4, Flux Kontext, Wan 2.5, OpenAI GPT Image 1.
 * API: https://elevenlabs.io/docs/overview/capabilities/image-video
 * Uses async pattern: POST to create generation, then poll until completed/failed.
 */
class ElevenLabsImageDriver implements ImageContract
{
    private const CREATE_ENDPOINT = 'v1/image-video/generations';
    private const POLL_ENDPOINT = 'v1/image-video/generations';

    public function __construct(private array $cfg) {}

    public function create(ImageRequest $request): ImageResponse
    {
        $options = $request->options ?? [];
        $http = $this->makeHttpClient();
        $model = $request->model ?? ($this->cfg['image_model'] ?? 'google-nano-banana');
        $payload = [
            'type' => 'image',
            'model_id' => $model,
            'prompt' => $request->prompt,
            'aspect_ratio' => $this->ratioFromSize($request->size),
            'num_images' => (int) ($options['num_images'] ?? 1),
        ];
        if (! empty($options['reference_image_urls'] ?? [])) {
            $payload['reference_image_urls'] = $options['reference_image_urls'];
        }
        if (! empty($options['negative_prompt'] ?? null)) {
            $payload['negative_prompt'] = $options['negative_prompt'];
        }

        $res = $http->post(self::CREATE_ENDPOINT, $payload);
        if (! $res->successful()) {
            throw new ProviderException(
                'ElevenLabs image create failed',
                'elevenlabs',
                $res->status(),
                $res->json() ?? ['body' => $res->body()],
            );
        }

        $generationId = $res->json('id') ?? $res->json('generation_id');
        if (! $generationId) {
            throw new ProviderException(
                'ElevenLabs image create missing generation id',
                'elevenlabs',
                $res->status(),
                $res->json(),
            );
        }

        $final = $this->pollUntilComplete($http, $generationId, $options['poll'] ?? []);
        $images = $this->extractImages($final);
        if (empty($images)) {
            throw new ProviderException(
                'ElevenLabs image generation returned no output',
                'elevenlabs',
                502,
                $final,
            );
        }
        return new ImageResponse($images, $final);
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        $options = $request->options ?? [];
        $refs = $options['reference_image_urls'] ?? [];
        if ($request->imagePath !== null) {
            $refs[] = $this->imagePathToUrl($request->imagePath);
            $options['reference_image_urls'] = array_values($refs);
        }
        $model = $request->model ?? 'flux-1-kontext-pro';
        $modifyRequest = new ImageRequest(
            prompt: $request->prompt,
            imagePath: null,
            maskPath: $request->maskPath,
            size: $request->size,
            model: $model,
            options: $options,
        );
        return $this->create($modifyRequest);
    }

    public function upscale(ImageRequest $request): ImageResponse
    {
        $options = $request->options ?? [];
        $sourceUrl = $options['source_url'] ?? null;
        if (! $sourceUrl && $request->imagePath !== null) {
            $sourceUrl = $this->imagePathToUrl($request->imagePath);
        }
        if (! $sourceUrl) {
            throw new ProviderException(
                'ElevenLabs upscale requires source_url in options or imagePath',
                'elevenlabs',
                400,
                [],
            );
        }

        $http = $this->makeHttpClient();
        $payload = [
            'type' => 'upscale',
            'model_id' => 'topaz-upscale',
            'source_url' => $sourceUrl,
            'scale' => (float) ($options['scale'] ?? 2),
        ];

        $res = $http->post(self::CREATE_ENDPOINT, $payload);
        if (! $res->successful()) {
            throw new ProviderException(
                'ElevenLabs image upscale failed',
                'elevenlabs',
                $res->status(),
                $res->json() ?? ['body' => $res->body()],
            );
        }

        $generationId = $res->json('id') ?? $res->json('generation_id');
        if (! $generationId) {
            throw new ProviderException(
                'ElevenLabs upscale missing generation id',
                'elevenlabs',
                $res->status(),
                $res->json(),
            );
        }

        $final = $this->pollUntilComplete($http, $generationId, $options['poll'] ?? []);
        $images = $this->extractImages($final);
        if (empty($images)) {
            throw new ProviderException(
                'ElevenLabs upscale returned no output',
                'elevenlabs',
                502,
                $final,
            );
        }
        return new ImageResponse($images, $final);
    }

    private function makeHttpClient(): PendingRequest
    {
        return HttpClientFactory::make($this->cfg, 'elevenlabs')
            ->withHeaders(['xi-api-key' => $this->cfg['api_key'] ?? '']);
    }

    private function ratioFromSize(string $size): string
    {
        if (preg_match('/^\d+:\d+$/', $size)) {
            return $size;
        }
        return match ($size) {
            '1024x1024', '2048x2048' => '1:1',
            '512x512' => '1:1',
            '1920x1080', '1280x720' => '16:9',
            '1080x1920', '720x1280' => '9:16',
            default => '1:1',
        };
    }

    /**
     * If imagePath is a URL, return as-is; otherwise assume local file and would need upload.
     * ElevenLabs typically expects URLs; for local files the caller should pass reference_image_urls with a publicly accessible URL.
     */
    private function imagePathToUrl(?string $imagePath): string
    {
        if (str_starts_with($imagePath ?? '', 'http://') || str_starts_with($imagePath ?? '', 'https://')) {
            return $imagePath;
        }
        throw new ProviderException(
            'ElevenLabs image reference must be a URL; pass reference_image_urls in options for URLs, or upload the file first',
            'elevenlabs',
            400,
            [],
        );
    }

    /** @return array<int, array{b64?: string, url?: string}> */
    private function extractImages(array $final): array
    {
        $outputs = $final['outputs'] ?? $final['data']['outputs'] ?? [];
        $images = [];
        foreach ($outputs as $out) {
            $url = is_array($out) ? ($out['url'] ?? $out['output_url'] ?? null) : null;
            if ($url) {
                $images[] = ['url' => $url];
            }
        }
        if (empty($images) && ! empty($final['output_url'])) {
            $images[] = ['url' => $final['output_url']];
        }
        return $images;
    }

    private function pollUntilComplete(PendingRequest $http, string $generationId, array $pollOptions): array
    {
        $interval = (int) ($pollOptions['interval'] ?? 3);
        $timeout = (int) ($pollOptions['timeout'] ?? 300);
        $url = self::POLL_ENDPOINT.'/'.$generationId;
        $started = time();

        do {
            $res = $http->get($url);
            if (! $res->successful()) {
                throw new ProviderException(
                    'ElevenLabs image generation status failed',
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
                    'ElevenLabs image generation failed',
                    'elevenlabs',
                    $res->status(),
                    $json,
                );
            }

            if ((time() - $started) > $timeout) {
                throw new ProviderException(
                    'ElevenLabs image generation timeout',
                    'elevenlabs',
                    504,
                    $json,
                );
            }
            sleep($interval);
        } while (true);
    }
}
