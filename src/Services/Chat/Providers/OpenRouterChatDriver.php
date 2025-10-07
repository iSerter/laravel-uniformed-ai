<?php

namespace Iserter\UniformedAI\Services\Chat\Providers;

use Iserter\UniformedAI\Services\Chat\Contracts\ChatContract;
use Iserter\UniformedAI\Services\Chat\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\Concerns\SupportsStreaming;

class OpenRouterChatDriver implements ChatContract
{
    use SupportsStreaming; // reuse SSE helper

    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg, 'openrouter');
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'openrouter/auto');

        $payload = [
            'model' => $model,
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];

        if ($request->tools) {
            // Map to OpenAI style tool schema which OpenRouter forwards
            $payload['tools'] = array_map(fn($t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'parameters' => $t->parameters,
                ],
            ], $request->tools);
            if ($request->toolChoice) $payload['tool_choice'] = $request->toolChoice;
        }

        // Remove nulls to avoid provider validation errors
        $payload = array_filter($payload, fn($v) => !is_null($v));

        $res = $http->post('chat/completions', $payload);
        if (!$res->successful()) {
            $raw = $res->json();
            if (is_array($raw)) { $raw['__http_status'] = $res->status(); }
            throw new ProviderException($raw['error']['message'] ?? 'OpenRouter error', 'openrouter', $res->status(), $raw);
        }

        $raw = $res->json();
        if (is_array($raw)) { $raw['__http_status'] = $res->status(); }
        $content = $res->json('choices.0.message.content') ?? '';
        $toolCalls = $res->json('choices.0.message.tool_calls');
        return new ChatResponse($content, $toolCalls, $model, $raw);
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
    $http = HttpClientFactory::make($this->cfg, 'openrouter');
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'openrouter/auto');
        $payload = [
            'model' => $model,
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
            'stream' => true,
            'temperature' => $request->temperature,
            'max_tokens' => $request->maxTokens,
        ];
        if ($request->tools) {
            $payload['tools'] = array_map(fn($t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t->name,
                    'description' => $t->description,
                    'parameters' => $t->parameters,
                ],
            ], $request->tools);
            if ($request->toolChoice) $payload['tool_choice'] = $request->toolChoice;
        }
        $payload = array_filter($payload, fn($v) => !is_null($v));

        $res = $http->withHeaders(['Accept' => 'text/event-stream'])->post('chat/completions', $payload);
        if (!$res->successful()) {
            $raw = $res->json();
            if (is_array($raw)) { $raw['__http_status'] = $res->status(); }
            throw new ProviderException('OpenRouter stream error', 'openrouter', $res->status(), $raw);
        }

        // OpenRouter sends SSE similar to OpenAI. We reuse generic parser but must ignore comment lines starting with ':'
        foreach (explode("\n\n", $res->body()) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || str_starts_with($chunk, ':')) continue; // comment / keep-alive
            if (!str_starts_with($chunk, 'data:')) continue;
            $json = trim(substr($chunk, 5));
            if ($json === '[DONE]') break;
            $delta = json_decode($json, true);
            if (!is_array($delta)) continue;
            // Mid-stream error event (finish_reason error) handling
            if (isset($delta['error'])) {
                $message = $delta['error']['message'] ?? 'OpenRouter stream error';
                $code = $delta['error']['code'] ?? 'stream_error';
                // Yield nothing more; raise exception so caller can handle mid-stream error semantics
                throw new ProviderException($message, 'openrouter', 200, $delta + ['error_code' => $code]);
            }
            $text = $delta['choices'][0]['delta']['content'] ?? '';
            if ($text !== '') {
                if ($onDelta) $onDelta($text, $delta);
                yield $text;
            }
            // finish event can include tool_calls etc; just stop if finish_reason provided
            $finish = $delta['choices'][0]['finish_reason'] ?? null;
            if ($finish !== null) {
                // end of stream
                if ($finish === 'error') {
                    throw new ProviderException('OpenRouter reported finish_reason=error mid-stream', 'openrouter', 200, $delta);
                }
            }
        }
    }
}
