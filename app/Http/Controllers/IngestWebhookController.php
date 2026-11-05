<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ingest\IngestService;
use App\Sources\SourceRegistry;
use App\Support\HmacSignature;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IngestWebhookController
{
    public function __construct(
        private readonly SourceRegistry $sources,
        private readonly IngestService $ingest,
        private readonly RateLimiter $limiter,
    ) {
    }

    public function __invoke(Request $request, string $source): JsonResponse
    {
        $config = $this->sources->get($source);
        if ($config === null) {
            return $this->error(404, 'unknown_source', sprintf('Source "%s" is not configured.', $source));
        }

        $maxBytes = (int) config('hookrelay.max_body_bytes');
        $body = $request->getContent();
        if (strlen($body) > $maxBytes) {
            return $this->error(413, 'payload_too_large', sprintf('Payload exceeds %d bytes.', $maxBytes));
        }

        if ($config->requiresSignature()
            && !HmacSignature::verify($body, (string) $config->secret, $request->header($config->signatureHeader))) {
            return $this->error(401, 'invalid_signature', sprintf('Missing or invalid %s header.', $config->signatureHeader));
        }

        $limit = (int) config('hookrelay.rate_limit_per_minute');
        if (!$this->limiter->attempt('ingest:' . $source, $limit, static fn () => true, 60)) {
            return $this->error(429, 'rate_limited', 'Too many webhooks for this source, slow down.')
                ->header('Retry-After', (string) $this->limiter->availableIn('ingest:' . $source));
        }

        $result = $this->ingest->ingest($config, self::flattenHeaders($request), $body);

        return new JsonResponse([
            'id' => $result->event->id,
            'duplicate' => $result->duplicate,
            'deliveries' => $result->deliveries,
        ], $result->duplicate ? 200 : 202);
    }

    /**
     * @return array<string, string>
     */
    private static function flattenHeaders(Request $request): array
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower((string) $name)] = implode(', ', array_map('strval', (array) $values));
        }
        unset($headers['cookie'], $headers['authorization']);
        ksort($headers);

        return $headers;
    }

    private function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
