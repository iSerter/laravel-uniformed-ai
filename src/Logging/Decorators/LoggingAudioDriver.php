<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse, AudioTranscriptionRequest, AudioTranscriptionResponse};

class LoggingAudioDriver extends AbstractLoggingDriver implements AudioContract
{
    public function __construct(private AudioContract $inner, string $provider)
    { parent::__construct($provider, 'audio'); }

    public function speak(AudioRequest $request): AudioResponse
    {
        $draft = $this->startDraft('speak', $this->req($request), $request->model ?? null);
    return $this->runOperation($draft, fn() => $this->inner->speak($request), fn(AudioResponse $r) => ['audio_b64' => $this->truncate($r->b64Audio)]);
    }

    public function transcribe(AudioTranscriptionRequest $request): AudioTranscriptionResponse
    {
        $draft = $this->startDraft('transcribe', $this->transcribeReq($request), $request->model ?? null);
        return $this->runOperation($draft, fn() => $this->inner->transcribe($request), fn(AudioTranscriptionResponse $r) => ['text' => $r->text, 'language' => $r->language, 'duration' => $r->duration]);
    }

    protected function req(AudioRequest $r): array
    { return ['voice' => $r->voice, 'format' => $r->format, 'text_chars' => strlen($r->text)]; }

    protected function transcribeReq(AudioTranscriptionRequest $r): array
    { 
        $audioSize = $r->isBase64 ? strlen($r->audioFile) : (file_exists($r->audioFile) ? filesize($r->audioFile) : 0);
        return ['language' => $r->language, 'audio_size_bytes' => $audioSize, 'is_base64' => $r->isBase64]; 
    }

    protected function truncate(string $b64): string
    { $limit = 8000; return strlen($b64) > $limit ? substr($b64,0,$limit-15).'...(truncated)' : $b64; }

    public function getAvailableVoices(bool $refresh = false): array
    {
        // Metadata fetch: we can optionally log as a separate operation later; for now pass-through.
        return $this->inner->getAvailableVoices($refresh);
    }
}
