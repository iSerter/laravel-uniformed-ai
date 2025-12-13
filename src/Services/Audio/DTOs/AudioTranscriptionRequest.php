<?php

namespace Iserter\UniformedAI\Services\Audio\DTOs;

class AudioTranscriptionRequest
{
    public function __construct(
        public string $audioFile, // Path to audio file or base64 encoded content
        public ?string $language = null, // ISO-639-1 code (e.g., 'en', 'es')
        public ?string $model = null, // Specific model to use (provider-dependent)
        public bool $isBase64 = false, // Whether audioFile is base64 encoded
        public ?array $options = null, // Additional provider-specific options
    ) {}
}
