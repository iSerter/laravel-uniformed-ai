<?php

namespace Iserter\UniformedAI\Services\Image\Providers;

use Illuminate\Http\Client\PendingRequest;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;

/**
 * Google (Gemini image models, e.g. gemini-3.1-flash-lite-image / "Nano Banana") image driver.
 * Single endpoint for both text-to-image and image-editing: POST /v1beta/interactions,
 * with `input` holding text and/or inline base64 image parts.
 *
 * @see https://ai.google.dev/gemini-api/docs/generate-content/image-generation
 */
class GoogleImageDriver implements ImageContract
{
    private const ENDPOINT = 'v1beta/interactions';

    public function __construct(private array $cfg) {}

    public function create(ImageRequest $request): ImageResponse
    {
        $payload = [
            'model' => $this->resolveModel($request),
            'input' => [
                ['type' => 'text', 'text' => $request->prompt],
            ],
            'response_format' => $this->responseFormat($request->size),
        ];

        return $this->send($payload, 'Google image error');
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        $payload = [
            'model' => $this->resolveModel($request),
            'input' => [
                ['type' => 'image', 'mime_type' => 'image/png', 'data' => base64_encode(file_get_contents($request->imagePath))],
                ['type' => 'text', 'text' => $request->prompt],
            ],
            'response_format' => $this->responseFormat($request->size),
        ];

        return $this->send($payload, 'Google image edit error');
    }

    public function upscale(ImageRequest $request): ImageResponse
    {
        return $this->create(new ImageRequest(
            prompt: $request->prompt,
            imagePath: $request->imagePath,
            size: $request->options['size'] ?? '2048x2048',
            model: $request->model,
            options: $request->options,
        ));
    }

    private function send(array $payload, string $errorMessage): ImageResponse
    {
        $res = $this->makeHttpClient()->post(self::ENDPOINT, $payload);

        if (! $res->successful()) {
            throw new ProviderException($errorMessage, 'google', $res->status(), $res->json() ?? ['body' => $res->body()]);
        }

        $json = $res->json() ?? [];
        $images = $this->extractImages($json);

        if (empty($images)) {
            throw new ProviderException('Google image generation returned no output', 'google', 502, $json);
        }

        return new ImageResponse($images, $json);
    }

    private function makeHttpClient(): PendingRequest
    {
        return HttpClientFactory::make($this->cfg, 'google')
            ->withHeaders(['x-goog-api-key' => $this->cfg['api_key'] ?? '']);
    }

    private function resolveModel(ImageRequest $request): string
    {
        return $request->model ?? ($this->cfg['image']['model'] ?? 'gemini-3.1-flash-lite-image');
    }

    /** @return array{type: string, aspect_ratio: string, image_size: string} */
    private function responseFormat(string $size): array
    {
        [$ratio, $imageSize] = match ($size) {
            '1024x1024' => ['1:1', '1K'],
            '2048x2048' => ['1:1', '2K'],
            '1024x1536' => ['3:4', '1K'],
            '1536x1024' => ['4:3', '1K'],
            '1080x1920', '720x1280' => ['9:16', '1K'],
            '1920x1080', '1280x720' => ['16:9', '1K'],
            default => ['1:1', '1K'],
        };

        return ['type' => 'image', 'aspect_ratio' => $ratio, 'image_size' => $imageSize];
    }

    /** @return array<int, array{b64?: string}> */
    private function extractImages(array $json): array
    {
        $images = [];

        if (! empty($json['output_image']['data'])) {
            $images[] = ['b64' => $json['output_image']['data']];
        }

        foreach ($json['steps'] ?? [] as $step) {
            foreach ($step['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'image' && ! empty($block['data'])) {
                    $images[] = ['b64' => $block['data']];
                }
            }
        }

        return $images;
    }
}
