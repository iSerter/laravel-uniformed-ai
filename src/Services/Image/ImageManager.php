<?php

namespace Iserter\UniformedAI\Services\Image;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\Providers\OpenAIImageDriver;
use Iserter\UniformedAI\Support\Concerns\SupportsUsing;

class ImageManager extends Manager implements ImageContract
{
    use SupportsUsing;
    public function getDefaultDriver() { return config('uniformed-ai.defaults.image'); }

    public function create(\Iserter\UniformedAI\Services\Image\DTOs\ImageRequest $r): \Iserter\UniformedAI\Services\Image\DTOs\ImageResponse { return $this->driver()->create($r); }
    public function modify(\Iserter\UniformedAI\Services\Image\DTOs\ImageRequest $r): \Iserter\UniformedAI\Services\Image\DTOs\ImageResponse { return $this->driver()->modify($r); }
    public function upscale(\Iserter\UniformedAI\Services\Image\DTOs\ImageRequest $r): \Iserter\UniformedAI\Services\Image\DTOs\ImageResponse { return $this->driver()->upscale($r); }

    protected function createOpenaiDriver(): ImageContract
    {
        return new OpenAIImageDriver(config('uniformed-ai.providers.openai'));
    }
}
