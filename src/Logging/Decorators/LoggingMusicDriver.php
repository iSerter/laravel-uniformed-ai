<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Music\Contracts\MusicContract;
use Iserter\UniformedAI\Services\Music\DTOs\{MusicRequest, MusicResponse};

class LoggingMusicDriver extends AbstractLoggingDriver implements MusicContract
{
    public function __construct(private MusicContract $inner, string $provider)
    { parent::__construct($provider, 'music'); }

    public function compose(MusicRequest $request): MusicResponse
    {
        $draft = $this->startDraft('compose', $this->req($request), $request->model);
    return $this->runOperation($draft, fn() => $this->inner->compose($request), fn(MusicResponse $r) => ['music_b64' => $this->truncate($r->b64Audio)]);
    }

    protected function req(MusicRequest $r): array
    { return ['prompt' => $r->prompt, 'model' => $r->model]; }

    protected function truncate(string $b64): string
    { $limit = 8000; return strlen($b64) > $limit ? substr($b64,0,$limit-15).'...(truncated)' : $b64; }
}
