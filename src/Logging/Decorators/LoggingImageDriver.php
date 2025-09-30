<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};

class LoggingImageDriver extends AbstractLoggingDriver implements ImageContract
{
    public function __construct(private ImageContract $inner, string $provider)
    { parent::__construct($provider, 'image'); }

    public function create(ImageRequest $request): ImageResponse
    {
        $draft = $this->startDraft('create', $this->req($request), $request->model);
        return $this->runOperation($draft, fn() => $this->inner->create($request), fn(ImageResponse $r) => ['images' => $this->truncateImages($r->images)]);
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        $draft = $this->startDraft('modify', $this->req($request), $request->model);
        return $this->runOperation($draft, fn() => $this->inner->modify($request), fn(ImageResponse $r) => ['images' => $this->truncateImages($r->images)]);
    }

    public function upscale(ImageRequest $request): ImageResponse
    {
        $draft = $this->startDraft('upscale', $this->req($request), $request->model);
        return $this->runOperation($draft, fn() => $this->inner->upscale($request), fn(ImageResponse $r) => ['images' => $this->truncateImages($r->images)]);
    }

    protected function req(ImageRequest $r): array
    { return ['prompt' => $r->prompt, 'model' => $r->model, 'size' => $r->size]; }

    protected function truncateImages(array $images): array
    {
        $limit = (int) config('uniformed-ai.logging.truncate.response_chars', 40000) / max(count($images), 1);
        return array_map(function ($img) use ($limit) {
            $b64 = $img['b64'] ?? '';
            if (strlen($b64) > $limit) $b64 = substr($b64, 0, $limit - 15) . '...(truncated)';
            return ['b64' => $b64];
        }, $images);
    }
}
