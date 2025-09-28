<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Image\ImageContract;
use Iserter\UniformedAI\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Drivers\OpenAI\OpenAIImageDriver;

class ImageManager extends Manager implements ImageContract
{
    public function getDefaultDriver() { return config('uniformed-ai.defaults.image'); }

    public function create(ImageRequest $r): ImageResponse { return $this->driver()->create($r); }
    public function modify(ImageRequest $r): ImageResponse { return $this->driver()->modify($r); }
    public function upscale(ImageRequest $r): ImageResponse { return $this->driver()->upscale($r); }

    protected function createOpenaiDriver(): ImageContract
    {
        $cfg = config('uniformed-ai.providers.openai');
        if (empty($cfg['base_url'])) { $cfg['base_url'] = 'https://api.openai.com/v1'; }
        return new OpenAIImageDriver($cfg);
    }
}
