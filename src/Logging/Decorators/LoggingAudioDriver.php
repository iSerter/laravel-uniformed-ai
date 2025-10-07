<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Audio\Contracts\AudioContract;
use Iserter\UniformedAI\Services\Audio\DTOs\{AudioRequest, AudioResponse};

class LoggingAudioDriver extends AbstractLoggingDriver implements AudioContract
{
    public function __construct(private AudioContract $inner, string $provider)
    { parent::__construct($provider, 'audio'); }

    public function speak(AudioRequest $request): AudioResponse
    {
        $draft = $this->startDraft('speak', $this->req($request), $request->model ?? null);
    return $this->runOperation($draft, fn() => $this->inner->speak($request), fn(AudioResponse $r) => ['audio_b64' => $this->truncate($r->b64Audio)]);
    }

    protected function req(AudioRequest $r): array
    { return ['voice' => $r->voice, 'format' => $r->format, 'text_chars' => strlen($r->text)]; }

    protected function truncate(string $b64): string
    { $limit = 8000; return strlen($b64) > $limit ? substr($b64,0,$limit-15).'...(truncated)' : $b64; }

    public function getAvailableVoices(bool $refresh = false): array
    {
        // Metadata fetch: we can optionally log as a separate operation later; for now pass-through.
        return $this->inner->getAvailableVoices($refresh);
    }
}
