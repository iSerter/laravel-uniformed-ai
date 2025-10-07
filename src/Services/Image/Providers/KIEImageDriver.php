<?php

namespace Iserter\UniformedAI\Services\Image\Providers;

use Iserter\UniformedAI\Services\Image\Contracts\ImageContract;
use Iserter\UniformedAI\Services\Image\DTOs\{ImageRequest, ImageResponse};
use Iserter\UniformedAI\Exceptions\ProviderException;
use Iserter\UniformedAI\Support\HttpClientFactory;

/**
 * KIE Image driver supporting two model namespaces in our catalog:
 *  - mj  (Midjourney style API: /api/v1/mj/* endpoints)
 *  - 4o  (GPT-4o image API: /api/v1/gpt4o-image/* endpoints)
 *
 * The KIE platform is asynchronous. We:
 *  1. POST a generate/upscale request -> receive taskId
 *  2. Poll record-info until successFlag is a terminal value.
 *
 * successFlag semantics (union of mj + 4o docs):
 *  - 0 => processing
 *  - 1 => success
 *  - 2 => failed
 *  - 3 => (mj specific additional failure state)
 */
class KIEImageDriver implements ImageContract
{
    public function __construct(private array $cfg) {}

    public function create(ImageRequest $request): ImageResponse
    {
        $model = $request->model ?? 'mj'; // default to mj path if unspecified
        $http  = HttpClientFactory::make($this->cfg, 'kie');

        [$generateEndpoint, $statusEndpoint] = $this->endpointsForModel($model);

        $payload = $this->buildGeneratePayload($model, $request);
        $res = $http->post($generateEndpoint, $payload);
        if (!$res->successful()) {
            throw new ProviderException('KIE image create error', 'kie', $res->status(), $res->json());
        }
        $taskId = $res->json('data.taskId');
        if (!$taskId) {
            throw new ProviderException('KIE image create missing taskId', 'kie', $res->status(), $res->json());
        }

        $final = $this->pollUntilComplete($http, $statusEndpoint, $taskId, $model, $request->options['poll'] ?? []);

        $images = $this->extractImages($model, $final);
        return new ImageResponse($images, $final);
    }

    public function modify(ImageRequest $request): ImageResponse
    {
        // For now treat modify same as create but allow fileUrl/mask in options
        return $this->create($request);
    }

    public function upscale(ImageRequest $request): ImageResponse
    {
        $model = $request->model ?? 'mj';
        if ($model !== 'mj') {
            // 4o not providing explicit upscale endpoint; fallback to create with maybe larger size
            return $this->create($request);
        }
        $http = HttpClientFactory::make($this->cfg, 'kie');
        [$generateEndpoint, $statusEndpoint] = $this->endpointsForModel($model);

        $upscaleEndpoint = 'api/v1/mj/upscale';
        $taskId = $request->options['taskId'] ?? null; // original task to upscale
        $index  = $request->options['index'] ?? 1; // which quadrant 1-4
        if (!$taskId) {
            throw new ProviderException('KIE upscale requires original mj taskId in options[taskId]', 'kie', 400);
        }
        $payload = [ 'taskId' => $taskId, 'index' => $index ];
        $res = $http->post($upscaleEndpoint, $payload);
        if (!$res->successful()) {
            throw new ProviderException('KIE mj upscale error', 'kie', $res->status(), $res->json());
        }
        $newTaskId = $res->json('data.taskId');
        if (!$newTaskId) {
            throw new ProviderException('KIE mj upscale missing taskId', 'kie', $res->status(), $res->json());
        }
        $final = $this->pollUntilComplete($http, $statusEndpoint, $newTaskId, $model, $request->options['poll'] ?? []);
        $images = $this->extractImages($model, $final);
        return new ImageResponse($images, $final);
    }

    private function endpointsForModel(string $model): array
    {
        if ($model === '4o') {
            return ['api/v1/gpt4o-image/generate', 'api/v1/gpt4o-image/record-info'];
        }
        // default mj
        return ['api/v1/mj/generate', 'api/v1/mj/record-info'];
    }

    private function buildGeneratePayload(string $model, ImageRequest $r): array
    {
        if ($model === '4o') {
            $payload = [
                'prompt' => $r->prompt,
            ];
            // 4o uses size ratios like 1:1, 3:2, 2:3; map from typical size if given
            $payload['size'] = $this->ratioFromSize($r->size);
            if (!empty($r->options['filesUrl'])) $payload['filesUrl'] = $r->options['filesUrl'];
            if (!empty($r->options['maskUrl'])) $payload['maskUrl'] = $r->options['maskUrl'];
            if (!empty($r->options['nVariants'])) $payload['nVariants'] = $r->options['nVariants'];
            if (!empty($r->options['isEnhance'])) $payload['isEnhance'] = (bool) $r->options['isEnhance'];
            if (!empty($r->options['enableFallback'])) $payload['enableFallback'] = (bool) $r->options['enableFallback'];
            if (!empty($r->options['callBackUrl'])) $payload['callBackUrl'] = $r->options['callBackUrl'];
            return $payload;
        }

        // mj variant
        $payload = [
            'taskType' => $r->options['taskType'] ?? ($r->imagePath ? 'mj_img2img' : 'mj_txt2img'),
            'prompt' => $r->prompt,
            'aspectRatio' => $this->ratioFromSize($r->size),
        ];
        if (!empty($r->options['speed'])) $payload['speed'] = $r->options['speed'];
        if (!empty($r->options['version'])) $payload['version'] = $r->options['version'];
        if (!empty($r->options['stylization'])) $payload['stylization'] = $r->options['stylization'];
        if (!empty($r->options['callBackUrl'])) $payload['callBackUrl'] = $r->options['callBackUrl'];
        if (!empty($r->options['fileUrl'])) $payload['fileUrl'] = $r->options['fileUrl']; // single image to image
        if (!empty($r->options['fileUrls'])) $payload['fileUrls'] = $r->options['fileUrls']; // possible future multi input
        return $payload;
    }

    private function ratioFromSize(string $size): string
    {
        // Accept already ratio form
        if (preg_match('/^\d+:\d+$/', $size)) return $size;
        // Map square defaults
        return match($size) {
            '1024x1024','2048x2048' => '1:1',
            '512x512' => '1:1',
            '1920x1080','1280x720' => '16:9',
            default => '1:1',
        };
    }

    /**
     * Poll status endpoint until terminal successFlag.
     * @return array provider raw final response json
     */
    private function pollUntilComplete($http, string $statusEndpoint, string $taskId, string $model, array $pollOptions): array
    {
        $sleepSeconds = (int)($pollOptions['interval'] ?? ($model === '4o' ? 5 : 15));
        $timeoutSeconds = (int)($pollOptions['timeout'] ?? 600); // 10 min default
        $started = time();
        $url = $statusEndpoint.'?taskId='.urlencode($taskId);
        do {
            $res = $http->get($url);
            if (!$res->successful()) {
                throw new ProviderException('KIE image status error', 'kie', $res->status(), $res->json());
            }
            $json = $res->json();
            $flag = $json['data']['successFlag'] ?? null;
            if (in_array($flag, [1,2,3], true)) {
                return $json;
            }
            if ((time() - $started) > $timeoutSeconds) {
                throw new ProviderException('KIE image generation timeout', 'kie', 504, $json);
            }
            sleep($sleepSeconds);
        } while (true);
    }

    /**
     * Extract unified images array from final provider status response.
     * - mj: resultInfoJson.resultUrls[].resultUrl
     * - 4o: response.result_urls[]
     */
    private function extractImages(string $model, array $final): array
    {
        $data = $final['data'] ?? [];
        $images = [];
        if ($model === '4o') {
            $urls = $data['response']['result_urls'] ?? [];
            foreach ($urls as $u) $images[] = ['url' => $u];
        } else { // mj
            $resultInfo = $data['resultInfoJson']['resultUrls'] ?? [];
            foreach ($resultInfo as $entry) {
                if (is_array($entry) && !empty($entry['resultUrl'])) {
                    $images[] = ['url' => $entry['resultUrl']];
                }
            }
        }
        return $images;
    }
}
