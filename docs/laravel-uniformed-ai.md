# iserter/laravel-uniformed-ai

A Laravel package that exposes a single, uniform API over multiple AI providers (OpenAI, OpenRouter, Google AI Studio, KIE.AI, PIAPI.AI, Tavily, ElevenLabs, etc.).

> Namespace: `Iserter\UniformedAI\`

## Goals

* **Uniform Contracts** for Chat, Images, Audio/Speech, Music, and Web Search.
* **Driver/Manager pattern** (like `filesystem`/`queue`) so each service uses a configured provider.
* **DTOs** for request/response payloads.
* **Streaming** and **tool/function calling** for Chat.
* **Retries, rate limiting, caching**, and **sane error mapping** across providers.
* **Extensible**: add custom providers with minimal friction.

---

## Directory Layout

```
laravel-uniformed-ai/
├─ composer.json
├─ config/uniformed-ai.php
├─ src/
│  ├─ UniformedAIServiceProvider.php
│  ├─ Facades/
│  │  └─ AI.php
│  ├─ Contracts/
│  │  ├─ Chat/ChatContract.php
│  │  ├─ Image/ImageContract.php
│  │  ├─ Audio/AudioContract.php
│  │  ├─ Music/MusicContract.php
│  │  └─ Search/SearchContract.php
│  ├─ DTOs/
│  │  ├─ ChatMessage.php
│  │  ├─ ChatTool.php
│  │  ├─ ChatRequest.php
│  │  ├─ ChatResponse.php
│  │  ├─ ImageRequest.php
│  │  ├─ ImageResponse.php
│  │  ├─ AudioRequest.php
│  │  ├─ AudioResponse.php
│  │  ├─ MusicRequest.php
│  │  ├─ MusicResponse.php
│  │  ├─ SearchQuery.php
│  │  └─ SearchResults.php
│  ├─ Exceptions/
│  │  ├─ UniformedAIException.php
│  │  ├─ ProviderException.php
│  │  ├─ AuthenticationException.php
│  │  ├─ RateLimitException.php
│  │  └─ ValidationException.php
│  ├─ Managers/
│  │  ├─ ChatManager.php
│  │  ├─ ImageManager.php
│  │  ├─ AudioManager.php
│  │  ├─ MusicManager.php
│  │  └─ SearchManager.php
│  ├─ Drivers/
│  │  ├─ OpenAI/
│  │  │  ├─ OpenAIChatDriver.php
│  │  │  ├─ OpenAIImageDriver.php
│  │  │  └─ Http/OpenAIClient.php
│  │  ├─ OpenRouter/
│  │  │  └─ OpenRouterChatDriver.php
│  │  ├─ Google/
│  │  │  └─ GoogleChatDriver.php
│  │  ├─ Tavily/
│  │  │  └─ TavilySearchDriver.php
│  │  ├─ ElevenLabs/
│  │  │  ├─ ElevenLabsAudioDriver.php
│  │  │  └─ Http/ElevenLabsClient.php
│  │  ├─ KIE/
│  │  │  └─ KIEChatDriver.php
│  │  └─ PIAPI/
│  │     ├─ PIAPIChatDriver.php
│  │     └─ PIAPIMusicDriver.php
│  ├─ Support/
│  │  ├─ Concerns/SupportsStreaming.php
│  │  ├─ HttpClientFactory.php
│  │  ├─ RateLimiter.php
│  │  ├─ CacheRepository.php
│  │  └─ Mapper.php
│  └─ Helpers/
│     └─ Arr.php
├─ tests/
│  ├─ ChatTest.php
│  ├─ ImageTest.php
│  ├─ AudioTest.php
│  ├─ MusicTest.php
│  └─ SearchTest.php
└─ README.md
```

---

## `composer.json`

```json
{
  "name": "iserter/laravel-uniformed-ai",
  "description": "Uniform AI API for Laravel (Chat, Images, Audio, Music, Search) across OpenAI, OpenRouter, Google AI Studio, KIE.AI, PIAPI.AI, Tavily, ElevenLabs, etc.",
  "type": "library",
  "license": "MIT",
  "require": {
    "php": ">=8.2",
    "illuminate/support": "^10.0|^11.0",
    "guzzlehttp/guzzle": "^7.7"
  },
  "autoload": {
    "psr-4": {
      "Iserter\\UniformedAI\\": "src/"
    }
  },
  "extra": {
    "laravel": {
      "providers": [
        "Iserter\\UniformedAI\\UniformedAIServiceProvider"
      ],
      "aliases": {
        "AI": "Iserter\\UniformedAI\\Facades\\AI"
      }
    }
  }
}
```

---

## Config: `config/uniformed-ai.php`

```php
<?php

return [
    'defaults' => [
        'chat'   => env('AI_CHAT_DRIVER', 'openai'),
        'image'  => env('AI_IMAGE_DRIVER', 'openai'),
        'audio'  => env('AI_AUDIO_DRIVER', 'elevenlabs'),
        'music'  => env('AI_MUSIC_DRIVER', 'piapi'),
        'search' => env('AI_SEARCH_DRIVER', 'tavily'),
    ],

    'providers' => [
        'openai' => [
            'api_key'  => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'chat'  => ['model' => env('OPENAI_CHAT_MODEL', 'gpt-4.1-mini')],
            'image' => ['model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1')],
        ],

        'openrouter' => [
            'api_key'  => env('OPENROUTER_API_KEY'),
            'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'chat' => ['model' => env('OPENROUTER_CHAT_MODEL', 'openrouter/auto')],
        ],

        'google' => [
            'api_key'  => env('GOOGLE_AI_API_KEY'),
            'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com'),
            'chat' => ['model' => env('GOOGLE_CHAT_MODEL', 'gemini-1.5-pro')],
        ],

        'kie' => [
            'api_key' => env('KIE_AI_API_KEY'),
            'base_url' => env('KIE_AI_BASE_URL'),
        ],

        'piapi' => [
            'api_key' => env('PIAPI_API_KEY'),
            'base_url' => env('PIAPI_BASE_URL'),
            'music' => ['model' => env('PIAPI_MUSIC_MODEL', 'music/default')]
        ],

        'tavily' => [
            'api_key'  => env('TAVILY_API_KEY'),
            'base_url' => env('TAVILY_BASE_URL', 'https://api.tavily.com'),
            'search' => ['max_results' => 5]
        ],

        'elevenlabs' => [
            'api_key'  => env('ELEVENLABS_API_KEY'),
            'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io'),
            'voice_id' => env('ELEVENLABS_VOICE_ID', 'Rachel'),
            'model'    => env('ELEVENLABS_MODEL', 'eleven_multilingual_v2'),
        ],
    ],

    'http' => [
        'timeout' => env('AI_HTTP_TIMEOUT', 60),
        'retries' => env('AI_HTTP_RETRIES', 2),
        'retry_delay_ms' => env('AI_HTTP_RETRY_DELAY_MS', 250),
    ],

    'cache' => [
        'store' => env('AI_CACHE_STORE', null), // null uses default; e.g. 'redis'
        'ttl'   => env('AI_CACHE_TTL', 3600),
    ],

    'rate_limit' => [
        // per provider (requests/min). Use 0 or null to disable
        'openai'      => env('AI_RL_OPENAI', 0),
        'openrouter'  => env('AI_RL_OPENROUTER', 0),
        'google'      => env('AI_RL_GOOGLE', 0),
        'kie'         => env('AI_RL_KIE', 0),
        'piapi'       => env('AI_RL_PIAPI', 0),
        'tavily'      => env('AI_RL_TAVILY', 0),
        'elevenlabs'  => env('AI_RL_ELEVENLABS', 0),
    ],
];
```

> **Env example**

```
OPENAI_API_KEY=...
OPENAI_CHAT_MODEL=gpt-4.1-mini
OPENAI_IMAGE_MODEL=gpt-image-1
OPENROUTER_API_KEY=...
GOOGLE_AI_API_KEY=...
TAVILY_API_KEY=...
ELEVENLABS_API_KEY=...
ELEVENLABS_VOICE_ID=...
```

---

## Service Provider & Facade

### `src/UniformedAIServiceProvider.php`

```php
<?php

namespace Iserter\UniformedAI;

use Illuminate\Support\ServiceProvider;
use Iserter\UniformedAI\Managers\{ChatManager, ImageManager, AudioManager, MusicManager, SearchManager};

class UniformedAIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/uniformed-ai.php', 'uniformed-ai');

        $this->app->singleton(ChatManager::class, fn($app) => new ChatManager($app));
        $this->app->singleton(ImageManager::class, fn($app) => new ImageManager($app));
        $this->app->singleton(AudioManager::class, fn($app) => new AudioManager($app));
        $this->app->singleton(MusicManager::class, fn($app) => new MusicManager($app));
        $this->app->singleton(SearchManager::class, fn($app) => new SearchManager($app));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/uniformed-ai.php' => config_path('uniformed-ai.php'),
        ], 'uniformed-ai-config');
    }
}
```

### `src/Facades/AI.php`

```php
<?php

namespace Iserter\UniformedAI\Facades;

use Illuminate\Support\Facades\Facade;
use Iserter\UniformedAI\Managers\{ChatManager, ImageManager, AudioManager, MusicManager, SearchManager};

/**
 * @method static ChatManager chat()
 * @method static ImageManager image()
 * @method static AudioManager audio()
 * @method static MusicManager music()
 * @method static SearchManager search()
 */
class AI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'iserter.uniformed-ai.facade';
    }
}
```

> **Facade binding** (add in ServiceProvider `register()`):

```php
$this->app->singleton('iserter.uniformed-ai.facade', function ($app) {
    return new class($app) {
        public function __construct(private $app) {}
        public function chat()  { return $this->app->make(\Iserter\UniformedAI\Managers\ChatManager::class); }
        public function image() { return $this->app->make(\Iserter\UniformedAI\Managers\ImageManager::class); }
        public function audio() { return $this->app->make(\Iserter\UniformedAI\Managers\AudioManager::class); }
        public function music() { return $this->app->make(\Iserter\UniformedAI\Managers\MusicManager::class); }
        public function search(){ return $this->app->make(\Iserter\UniformedAI\Managers\SearchManager::class); }
    };
});
```

---

## Contracts (Uniform APIs)

### Chat

`src/Contracts/Chat/ChatContract.php`

```php
<?php

namespace Iserter\UniformedAI\Contracts\Chat;

use Closure;
use Generator;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};

interface ChatContract
{
    public function send(ChatRequest $request): ChatResponse;

    /** Stream deltas. Callback receives partial string or structured delta array. */
    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator;
}
```

### Images

`src/Contracts/Image/ImageContract.php`

```php
<?php

namespace Iserter\UniformedAI\Contracts\Image;

use Iserter\UniformedAI\DTOs\{ImageRequest, ImageResponse};

interface ImageContract
{
    public function create(ImageRequest $request): ImageResponse;
    public function modify(ImageRequest $request): ImageResponse; // edit/inpaint
    public function upscale(ImageRequest $request): ImageResponse;
}
```

### Audio/Speech

`src/Contracts/Audio/AudioContract.php`

```php
<?php

namespace Iserter\UniformedAI\Contracts\Audio;

use Iserter\UniformedAI\DTOs\{AudioRequest, AudioResponse};

interface AudioContract
{
    public function speak(AudioRequest $request): AudioResponse; // text->speech
}
```

### Music

`src/Contracts/Music/MusicContract.php`

```php
<?php

namespace Iserter\UniformedAI\Contracts\Music;

use Iserter\UniformedAI\DTOs\{MusicRequest, MusicResponse};

interface MusicContract
{
    public function compose(MusicRequest $request): MusicResponse;
}
```

### Search

`src/Contracts/Search/SearchContract.php`

```php
<?php

namespace Iserter\UniformedAI\Contracts\Search;

use Iserter\UniformedAI\DTOs\{SearchQuery, SearchResults};

interface SearchContract
{
    public function query(SearchQuery $query): SearchResults;
}
```

---

## DTOs (Requests/Responses)

`src/DTOs/ChatMessage.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class ChatMessage
{
    public function __construct(
        public string $role, // system|user|assistant|tool
        public string $content = '',
        public ?array $attachments = null, // images/audio refs etc
        public ?string $name = null,
        public ?array $toolCalls = null,
    ) {}
}
```

`src/DTOs/ChatTool.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class ChatTool
{
    public function __construct(
        public string $name,
        public string $description,
        public array $parameters // JSON Schema
    ) {}
}
```

`src/DTOs/ChatRequest.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class ChatRequest
{
    /** @param ChatMessage[] $messages */
    public function __construct(
        public array $messages,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        /** @var ChatTool[]|null */
        public ?array $tools = null,
        public ?string $toolChoice = null, // auto|none|required|name
        public ?array $metadata = null,
    ) {}
}
```

`src/DTOs/ChatResponse.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class ChatResponse
{
    public function __construct(
        public string $content,
        public ?array $toolCalls = null,
        public ?string $model = null,
        public ?array $raw = null,
    ) {}
}
```

`src/DTOs/ImageRequest.php`

```php
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
```

`src/DTOs/ImageResponse.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class ImageResponse
{
    public function __construct(
        /** @var array<int, array{b64?:string,url?:string}> */
        public array $images,
        public ?array $raw = null,
    ) {}
}
```

`src/DTOs/AudioRequest.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class AudioRequest
{
    public function __construct(
        public string $text,
        public ?string $voice = null,
        public string $format = 'mp3',
        public ?string $model = null,
        public ?array $options = null,
    ) {}
}
```

`src/DTOs/AudioResponse.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class AudioResponse
{
    public function __construct(
        public string $b64Audio,
        public ?array $raw = null,
    ) {}
}
```

`src/DTOs/MusicRequest.php` / `MusicResponse.php` are analogous to Audio DTOs.

`src/DTOs/SearchQuery.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class SearchQuery
{
    public function __construct(
        public string $q,
        public int $maxResults = 5,
        public bool $includeAnswer = true,
        public ?array $filters = null,
    ) {}
}
```

`src/DTOs/SearchResults.php`

```php
<?php

namespace Iserter\UniformedAI\DTOs;

class SearchResults
{
    public function __construct(
        public ?string $answer,
        /** @var array<int, array{title:string,url:string,snippet?:string,score?:float}> */
        public array $results,
        public ?array $raw = null,
    ) {}
}
```

---

## Managers (Driver resolvers)

Pattern mirrors `Illuminate\Support\Manager`.

Example: `src/Managers/ChatManager.php`

```php
<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\Drivers\OpenAI\OpenAIChatDriver;
use Iserter\UniformedAI\Drivers\OpenRouter\OpenRouterChatDriver;
use Iserter\UniformedAI\Drivers\Google\GoogleChatDriver;
use Iserter\UniformedAI\Drivers\KIE\KIEChatDriver;
use Iserter\UniformedAI\Drivers\PIAPI\PIAPIChatDriver;

class ChatManager extends Manager implements ChatContract
{
    public function getDefaultDriver()
    {
        return config('uniformed-ai.defaults.chat');
    }

    // Uniform API
    public function send($request) { return $this->driver()->send($request); }
    public function stream($request, $onDelta = null): \Generator { return $this->driver()->stream($request, $onDelta); }

    // Drivers
    protected function createOpenaiDriver(): ChatContract
    {
        return new OpenAIChatDriver(config('uniformed-ai.providers.openai'));
    }

    protected function createOpenrouterDriver(): ChatContract
    {
        return new OpenRouterChatDriver(config('uniformed-ai.providers.openrouter'));
    }

    protected function createGoogleDriver(): ChatContract
    {
        return new GoogleChatDriver(config('uniformed-ai.providers.google'));
    }

    protected function createKieDriver(): ChatContract
    {
        return new KIEChatDriver(config('uniformed-ai.providers.kie'));
    }

    protected function createPiapiDriver(): ChatContract
    {
        return new PIAPIChatDriver(config('uniformed-ai.providers.piapi'));
    }
}
```

> Similar `ImageManager`, `AudioManager`, `MusicManager`, `SearchManager` with `createXDriver` methods per provider and uniform method proxies (`create()`, `modify()`, `upscale()`, etc.).

---

## Support Utilities

### HTTP client factory

`src/Support/HttpClientFactory.php`

```php
<?php

namespace Iserter\UniformedAI\Support;

use Illuminate\Support\Facades\Http;

class HttpClientFactory
{
    public static function make(array $cfg)
    {
        $client = Http::withOptions([
            'base_uri' => $cfg['base_url'] ?? null,
            'timeout' => (float) config('uniformed-ai.http.timeout'),
        ]);

        if (!empty($cfg['api_key'])) {
            $client = $client->withToken($cfg['api_key']);
        }

        $retries = (int) config('uniformed-ai.http.retries', 2);
        $delay   = (int) config('uniformed-ai.http.retry_delay_ms', 250);

        if ($retries > 0) {
            $client = $client->retry($retries, $delay, throw: false);
        }

        return $client;
    }
}
```

### Streaming trait

`src/Support/Concerns/SupportsStreaming.php`

```php
<?php

namespace Iserter\UniformedAI\Support\Concerns;

trait SupportsStreaming
{
    protected function sseToGenerator($response, ?\Closure $onDelta = null): \Generator
    {
        foreach (explode("\n\n", $response->body()) as $chunk) {
            $line = trim($chunk);
            if ($line === '' || !str_starts_with($line, 'data:')) continue;
            $json = substr($line, 5);
            $delta = json_decode($json, true);
            $text = $delta['choices'][0]['delta']['content'] ?? '';
            if ($onDelta) $onDelta($text, $delta);
            yield $text;
        }
    }
}
```

### Exceptions

`src/Exceptions/UniformedAIException.php`

```php
<?php

namespace Iserter\UniformedAI\Exceptions;

use Exception;

class UniformedAIException extends Exception
{
    public function __construct(
        string $message,
        public ?string $provider = null,
        public ?int $status = null,
        public ?array $raw = null
    ) {
        parent::__construct($message, $status ?? 0);
    }
}
```

(Other exception classes extend this and may be thrown by drivers.)

---

## Drivers (Examples)

### OpenAI Chat

`src/Drivers/OpenAI/OpenAIChatDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\OpenAI;

use Closure;
use Generator;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Support\Concerns\SupportsStreaming;

class OpenAIChatDriver implements ChatContract
{
    use SupportsStreaming;

    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['chat']['model'] ?? 'gpt-4.1-mini'),
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
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

        $res = $http->post('chat/completions', $payload);
        if (!$res->successful()) {
            throw new ProviderException($res->json('error.message') ?? 'OpenAI error', 'openai', $res->status(), $res->json());
        }

        $content = $res->json('choices.0.message.content') ?? '';
        $toolCalls = $res->json('choices.0.message.tool_calls');
        return new ChatResponse($content, $toolCalls, $payload['model'], $res->json());
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['chat']['model'] ?? 'gpt-4.1-mini'),
            'messages' => array_map(fn($m) => [
                'role' => $m->role,
                'content' => $m->content,
            ], $request->messages),
            'stream' => true,
        ];

        $res = $http->withHeaders(['Accept' => 'text/event-stream'])->post('chat/completions', $payload);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI stream error', 'openai', $res->status(), $res->json());
        }

        yield from $this->sseToGenerator($res, $onDelta);
    }
}
```

### OpenAI Images

`src/Drivers/OpenAI/OpenAIImageDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\OpenAI;

use Iserter\UniformedAI\Contracts\Image\ImageContract;
use Iserter\UniformedAI\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;

class OpenAIImageDriver implements ImageContract
{
    public function __construct(private array $cfg) {}

    public function create(ImageRequest $request): ImageResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'model' => $request->model ?? ($this->cfg['image']['model'] ?? 'gpt-image-1'),
            'prompt' => $request->prompt,
            'size' => $request->size,
            'response_format' => 'b64_json',
        ];
        $res = $http->post('images/generations', $payload);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI image error', 'openai', $res->status(), $res->json());
        }
        $images = array_map(fn($d) => ['b64' => $d['b64_json']], $res->json('data') ?? []);
        return new ImageResponse($images, $res->json());
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $multipart = [
            ['name' => 'model', 'contents' => $request->model ?? ($this->cfg['image']['model'] ?? 'gpt-image-1')],
            ['name' => 'image', 'contents' => fopen($request->imagePath, 'r')],
        ];
        if ($request->maskPath) $multipart[] = ['name' => 'mask', 'contents' => fopen($request->maskPath, 'r')];
        $multipart[] = ['name' => 'prompt', 'contents' => $request->prompt];
        $res = $http->asMultipart()->post('images/edits', $multipart);
        if (!$res->successful()) {
            throw new ProviderException('OpenAI image edit error', 'openai', $res->status(), $res->json());
        }
        $images = array_map(fn($d) => ['b64' => $d['b64_json']], $res->json('data') ?? []);
        return new ImageResponse($images, $res->json());
    }

    public function upscale(ImageRequest $request): ImageResponse
    {
        // Placeholder: depends on provider capability. Could re-call with higher size or different endpoint.
        return $this->create(new ImageRequest(
            prompt: $request->prompt,
            imagePath: $request->imagePath,
            size: $request->options['size'] ?? '2048x2048',
            model: $request->model,
            options: $request->options,
        ));
    }
}
```

### Google (Gemini) Chat

`src/Drivers/Google/GoogleChatDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\Google;

use Closure; use Generator;
use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class GoogleChatDriver implements ChatContract
{
    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'gemini-1.5-pro');
        $contents = array_map(fn($m) => [
            'role' => $m->role,
            'parts' => [['text' => $m->content]],
        ], $request->messages);

        $res = $http->post("v1beta/models/{$model}:generateContent?key=".$this->cfg['api_key'], [
            'contents' => $contents,
        ]);
        if (!$res->successful()) throw new ProviderException('Google AI error', 'google', $res->status(), $res->json());

        $text = $res->json('candidates.0.content.parts.0.text') ?? '';
        return new ChatResponse($text, null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?Closure $onDelta = null): Generator
    {
        // Gemini server-sent events or chunked response support can go here (omitted for brevity)
        yield from (function(){ if (false) yield ''; })();
    }
}
```

### OpenRouter Chat

`src/Drivers/OpenRouter/OpenRouterChatDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\OpenRouter;

use Iserter\UniformedAI\Contracts\Chat\ChatContract;
use Iserter\UniformedAI\DTOs\{ChatRequest, ChatResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class OpenRouterChatDriver implements ChatContract
{
    public function __construct(private array $cfg) {}

    public function send(ChatRequest $request): ChatResponse
    {
        $http = HttpClientFactory::make($this->cfg);
        $model = $request->model ?? ($this->cfg['chat']['model'] ?? 'openrouter/auto');
        $payload = [
            'model' => $model,
            'messages' => array_map(fn($m) => ['role'=>$m->role,'content'=>$m->content], $request->messages),
        ];
        $res = $http->post('chat/completions', $payload);
        if (!$res->successful()) throw new ProviderException('OpenRouter error', 'openrouter', $res->status(), $res->json());
        return new ChatResponse($res->json('choices.0.message.content') ?? '', null, $model, $res->json());
    }

    public function stream(ChatRequest $request, ?\Closure $onDelta = null): \Generator
    {
        // SSE similar to OpenAI
        yield from (function(){ if (false) yield ''; })();
    }
}
```

### Tavily Search

`src/Drivers/Tavily/TavilySearchDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\Tavily;

use Iserter\UniformedAI\Contracts\Search\SearchContract;
use Iserter\UniformedAI\DTOs\{SearchQuery, SearchResults};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class TavilySearchDriver implements SearchContract
{
    public function __construct(private array $cfg) {}

    public function query(SearchQuery $q): SearchResults
    {
        $http = HttpClientFactory::make($this->cfg);
        $payload = [
            'api_key' => $this->cfg['api_key'] ?? null,
            'query' => $q->q,
            'max_results' => $q->maxResults,
            'include_answer' => $q->includeAnswer,
            'search_depth' => 'advanced',
        ];
        $res = $http->post('search', $payload);
        if (!$res->successful()) throw new ProviderException('Tavily error', 'tavily', $res->status(), $res->json());
        $answer = $res->json('answer');
        $results = array_map(fn($r) => [
            'title' => $r['title'] ?? ($r['url'] ?? 'Result'),
            'url' => $r['url'],
            'snippet' => $r['content'] ?? null,
        ], $res->json('results') ?? []);
        return new SearchResults($answer, $results, $res->json());
    }
}
```

### ElevenLabs Audio

`src/Drivers/ElevenLabs/ElevenLabsAudioDriver.php`

```php
<?php

namespace Iserter\UniformedAI\Drivers\ElevenLabs;

use Iserter\UniformedAI\Contracts\Audio\AudioContract;
use Iserter\UniformedAI\DTOs\{AudioRequest, AudioResponse};
use Iserter\UniformedAI\Support\HttpClientFactory;
use Iserter\UniformedAI\Exceptions\ProviderException;

class ElevenLabsAudioDriver implements AudioContract
{
    public function __construct(private array $cfg) {}

    public function speak(AudioRequest $request): AudioResponse
    {
        $http = HttpClientFactory::make($this->cfg)
            ->withHeaders(['xi-api-key' => $this->cfg['api_key']]);

        $voice = $request->voice ?? ($this->cfg['voice_id'] ?? 'Rachel');
        $res = $http->post("v1/text-to-speech/{$voice}", [
            'text' => $request->text,
            'model_id' => $this->cfg['model'] ?? 'eleven_multilingual_v2',
            'voice_settings' => $request->options['voice_settings'] ?? null,
            'output_format' => $request->format,
        ]);

        if (!$res->successful()) throw new ProviderException('ElevenLabs error', 'elevenlabs', $res->status(), $res->json());

        $b64 = base64_encode($res->body());
        return new AudioResponse($b64, ['headers' => $res->headers()]);
    }
}
```

---

## Managers: other services

Example: `src/Managers/ImageManager.php`

```php
<?php

namespace Iserter\UniformedAI\Managers;

use Illuminate\Support\Manager;
use Iserter\UniformedAI\Contracts\Image\ImageContract;
use Iserter\UniformedAI\Drivers\OpenAI\OpenAIImageDriver;

class ImageManager extends Manager implements ImageContract
{
    public function getDefaultDriver() { return config('uniformed-ai.defaults.image'); }

    public function create($r) { return $this->driver()->create($r); }
    public function modify($r) { return $this->driver()->modify($r); }
    public function upscale($r) { return $this->driver()->upscale($r); }

    protected function createOpenaiDriver(): ImageContract
    {
        return new OpenAIImageDriver(config('uniformed-ai.providers.openai'));
    }

    // Add more providers as needed
}
```

> `AudioManager`, `MusicManager`, `SearchManager` follow the same approach, mapping provider names to driver classes and proxying uniform methods.

---

## Usage Examples

```php
use Iserter\UniformedAI\Facades\AI;
use Iserter\UniformedAI\DTOs\{ChatMessage, ChatRequest, ImageRequest, AudioRequest, SearchQuery};

// 1) Chat (single-shot)
$response = AI::chat()->send(new ChatRequest([
    new ChatMessage('system', 'You are a helpful assistant.'),
    new ChatMessage('user', 'Write a haiku about Laravel.'),
]));

// 2) Chat (streaming)
$gen = AI::chat()->stream(new ChatRequest([
    new ChatMessage('user', 'Stream a short poem, token by token.'),
]));
foreach ($gen as $delta) { echo $delta; }

// 3) Images
$img = AI::image()->create(new ImageRequest(prompt: 'A low-poly fox, 3D, studio light', size: '1024x1024'));
file_put_contents(storage_path('app/fox.png'), base64_decode($img->images[0]['b64']));

// 4) Audio / TTS
$tts = AI::audio()->speak(new AudioRequest(text: 'Hello world from Laravel.', voice: 'Rachel', format: 'mp3'));
file_put_contents(storage_path('app/hello.mp3'), base64_decode($tts->b64Audio));

// 5) Web Search (Tavily)
$results = AI::search()->query(new SearchQuery('Latest on PHP 8.3 features', maxResults: 5));
```

---

## Extensibility (Custom Providers)

Register a new driver at runtime:

```php
app(\Iserter\UniformedAI\Managers\ChatManager::class)->extend('myprovider', function($app) {
    return new \App\AI\Drivers\MyProviderChatDriver(config('services.myprovider'));
});
```

Write a driver by implementing the relevant Contract (e.g., `ChatContract`) and adapting its API in `send()`/`stream()`.

---

## Caching & Rate Limiting

* Provide an optional cache layer for **pure** operations (e.g., idempotent search queries) via `CacheRepository`.
* `RateLimiter` uses Laravel cache to enforce requests/min per provider. Drivers can call it before outbound requests.

```php
// Pseudo
$limiter->throttle('openai', 60, perMinute: config('uniformed-ai.rate_limit.openai'));
```

---

## Error Mapping

Normalize errors to `UniformedAIException` (with `provider`, `status`, `raw`). Examples:

* 401/403 → `AuthenticationException`
* 429 → `RateLimitException`
* 4xx/5xx → `ProviderException`

This allows upstream code to catch and branch without provider-specific logic.

---

## Testing (Pest/PHPUnit)

* Use Laravel HTTP fake: `Http::fake([...])` to assert payload shapes per provider and return canned JSON.
* Contract tests ensure each driver conforms to the uniform request/response DTOs.

---

## README (quick start)

* Install via Composer
* Publish config: `php artisan vendor:publish --tag=uniformed-ai-config`
* Set env keys per provider
* Quick usage code samples (as above)

---

## Notes & Future Work

* **Tool-Calling**: Add helper to loop provider tool calls until tool outputs are injected back into the messages.
* **Function/JSON Modes**: Expose `response_format: 'json_object'` (where supported) as a flag on `ChatRequest`.
* **Vision/Multimodal**: Extend `ChatMessage::$attachments` to support images/audio with data URIs or URLs.
* **Batching** & **Parallelism**: Consider Pools for concurrent calls (e.g., multiple images or searches).
* **Observability**: Add optional logging of token usage/latency (with secret redaction).
* **Laravel Scout integration** for RAG search pipelines.
