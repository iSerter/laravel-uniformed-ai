<?php

namespace Iserter\UniformedAI\Contracts\Image;

use Iserter\UniformedAI\DTOs\{ImageRequest, ImageResponse};

interface ImageContract
{
    public function create(ImageRequest $request): ImageResponse;
    public function modify(ImageRequest $request): ImageResponse; // edit/inpaint
    public function upscale(ImageRequest $request): ImageResponse;
}
