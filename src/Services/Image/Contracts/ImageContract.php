<?php

namespace Iserter\UniformedAI\Services\Image\Contracts;

use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};

interface ImageContract
{
    public function create(ImageRequest $request): ImageResponse;
    public function modify(ImageRequest $request): ImageResponse; // edit/inpaint
    public function upscale(ImageRequest $request): ImageResponse;
}
