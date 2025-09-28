<?php

namespace Iserter\UniformedAI\Services\Image\DTOs;

class ImageResponse
{
    public function __construct(
        /** @var array<int, array{b64?:string,url?:string}> */
        public array $images,
        public ?array $raw = null,
    ) {}
}
