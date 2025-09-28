<?php

namespace Iserter\UniformedAI\Services\Image\Providers;

use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;

class OpenAIImageDriver implements ImageContract
{
    public function __construct(private array $cfg) {}

    public function create(ImageRequest $request): ImageResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['image']['model'] ?? 'gpt-image-1'),
            'prompt' => $request->prompt,
            'size' => $request->size,
            'response_format' => 'b64_json',
        ];
        $res = $http->post('images/generations', $payload);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI image error', 'openai', $res->status(), $res->json());
        }
        $images = array_map(fn($d) => ['b64' => $d['b64_json']], $res->json('data') ?? []);
        return new ImageResponse($images, $res->json());
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $multipart = [
            ['name' => 'model', 'contents' => $request->model ?? ($this->cfg['image']['model'] ?? 'gpt-image-1')],
            ['name' => 'image', 'contents' => fopen($request->imagePath, 'r')],
        ];
        if ($request->maskPath) $multipart[] = ['name' => 'mask', 'contents' => fopen($request->maskPath, 'r')];
        $multipart[] = ['name' => 'prompt', 'contents' => $request->prompt];
        $res = $http->asMultipart()->post('images/edits', $multipart);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI image edit error', 'openai', $res->status(), $res->json());
        }
        $images = array_map(fn($d) => ['b64' => $d['b64_json']], $res->json('data') ?? []);
        return new ImageResponse($images, $res->json());
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
}
