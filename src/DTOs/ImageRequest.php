<?php

namespace Iserter\UniformedAI\DTOs;

class ImageRequest
{
    public function __construct(
        public string $prompt,
        public ?string $imagePath = null,
        public ?string $maskPath = null,
        public string $size = '1024x1024',
        public ?string $model = null,
        public ?array $options = null,
    ) {}
}
