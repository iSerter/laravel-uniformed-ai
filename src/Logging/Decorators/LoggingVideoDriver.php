<?php

namespace Iserter\UniformedAI\Logging\Decorators;

use Iserter\UniformedAI\Logging\AbstractLoggingDriver;
use Iserter\UniformedAI\Services\Video\Contracts\VideoContract;
use Iserter\UniformedAI\Services\Video\DTOs\{VideoRequest, VideoResponse};

class LoggingVideoDriver extends AbstractLoggingDriver implements VideoContract
{
    public function __construct(private VideoContract $inner, string $provider)
    {
        parent::__construct($provider, 'video');
    }

    public function generate(VideoRequest $request): VideoResponse
    {
        $draft = $this->startDraft('generate', $this->req($request), $request->model);
        return $this->runOperation($draft, fn () => $this->inner->generate($request), fn (VideoResponse $r) => $this->responseSummary($r));
    }

    public function edit(VideoRequest $request): VideoResponse
    {
        $draft = $this->startDraft('edit', $this->req($request), $request->model);
        return $this->runOperation($draft, fn () => $this->inner->edit($request), fn (VideoResponse $r) => $this->responseSummary($r));
    }

    protected function req(VideoRequest $r): array
    {
        return [
            'prompt' => $r->prompt,
            'model' => $r->model,
            'duration_seconds' => $r->durationSeconds,
        ];
    }

    protected function responseSummary(VideoResponse $r): array
    {
        return [
            'has_url' => $r->hasUrl(),
            'has_b64' => $r->hasB64Video(),
            'format' => $r->format,
        ];
    }
}
