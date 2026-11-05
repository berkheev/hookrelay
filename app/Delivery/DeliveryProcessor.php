<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Event;
use App\Queue\DeliveryQueue;
use App\Sources\SourceRegistry;
use App\Support\HmacSignature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

final class DeliveryProcessor
{
    private const FORWARDED_HEADERS = ['content-type'];

    public function __construct(
        private readonly Http $http,
        private readonly DeliveryQueue $queue,
        private readonly SourceRegistry $sources,
        private readonly RetryPolicy $retryPolicy,
        private readonly CircuitBreaker $circuitBreaker,
        private readonly LoggerInterface $logger,
        private readonly float $timeout = 10.0,
        private readonly float $connectTimeout = 5.0,
    ) {
    }

    public function process(int $deliveryId): ProcessResult
    {
        $delivery = $this->claim($deliveryId);
        if ($delivery === null) {
            return ProcessResult::Skipped;
        }

        $host = $delivery->destinationHost();
        $openFor = $this->circuitBreaker->openFor($host);
        if ($openFor > 0) {
            $this->reschedule($delivery, $openFor, countAttempt: false);

            return ProcessResult::Postponed;
        }

        $event = $delivery->event;
        $number = $delivery->attempts + 1;
        $startedAt = hrtime(true);
        $response = null;
        $error = null;

        try {
            $response = $this->http
                ->withHeaders($this->headersFor($event, $number))
                ->withBody($event->body, $event->header('content-type') ?? 'application/octet-stream')
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withoutRedirecting()
                ->post($delivery->destination);
        } catch (ConnectionException $e) {
            $error = self::shortError($e->getMessage());
        }

        $durationMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);
        $statusCode = $response?->status();

        if ($response !== null && !$response->successful()) {
            $error = sprintf('HTTP %d %s', $statusCode, $response->reason());
        }

        $delivery->attemptLog()->create([
            'number' => $number,
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'error' => $error,
            'response_excerpt' => $response === null ? null : self::excerpt($response),
            'created_at' => Carbon::now(),
        ]);

        if ($response !== null && $response->successful()) {
            $this->circuitBreaker->recordSuccess($host);
            $delivery->fill([
                'status' => DeliveryStatus::Delivered,
                'attempts' => $number,
                'next_attempt_at' => null,
                'locked_until' => null,
                'last_status_code' => $statusCode,
                'last_error' => null,
                'delivered_at' => Carbon::now(),
            ])->save();

            return ProcessResult::Delivered;
        }

        if ($statusCode === null || $statusCode >= 500) {
            $this->circuitBreaker->recordFailure($host);
        }

        $delivery->last_status_code = $statusCode;
        $delivery->last_error = $error;

        if ($this->retryPolicy->isRetryable($statusCode) && $this->retryPolicy->canRetry($number)) {
            $this->reschedule($delivery, $this->retryPolicy->delay($number), countAttempt: true);

            return ProcessResult::Retrying;
        }

        $delivery->fill([
            'status' => DeliveryStatus::Failed,
            'attempts' => $number,
            'next_attempt_at' => null,
            'locked_until' => null,
        ])->save();

        $this->logger->warning('Webhook delivery failed permanently', [
            'delivery_id' => $delivery->id,
            'event_id' => $event->id,
            'destination' => $delivery->destination,
            'attempts' => $number,
            'error' => $error,
        ]);

        return ProcessResult::Failed;
    }

    /**
     * Messages are delivered at least once, so the row decides whether this
     * consumer may send: it must be pending, due, and not claimed by another one.
     */
    private function claim(int $deliveryId): ?Delivery
    {
        $now = Carbon::now();

        $claimed = Delivery::query()
            ->whereKey($deliveryId)
            ->where('status', DeliveryStatus::Pending)
            ->where('next_attempt_at', '<=', $now->copy()->addSecond())
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', $now))
            ->update(['locked_until' => $now->copy()->addSeconds((int) ceil($this->timeout * 3))]);

        return $claimed === 1 ? Delivery::with('event')->find($deliveryId) : null;
    }

    private function reschedule(Delivery $delivery, int $delaySeconds, bool $countAttempt): void
    {
        $delaySeconds = $this->queue->effectiveDelay($delaySeconds);

        $delivery->fill([
            'attempts' => $delivery->attempts + ($countAttempt ? 1 : 0),
            'next_attempt_at' => Carbon::now()->addSeconds($delaySeconds),
            'locked_until' => null,
        ])->save();

        $this->queue->publish($delivery->id, $delaySeconds);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(Event $event, int $attempt): array
    {
        $source = $this->sources->get($event->source);
        $signatureHeader = strtolower($source->signatureHeader ?? '');
        $headers = [];

        foreach ($event->headers as $name => $value) {
            // The provider's signature can't be verified downstream, so it is dropped.
            if ((in_array($name, self::FORWARDED_HEADERS, true) || str_starts_with($name, 'x-')) && $name !== $signatureHeader) {
                $headers[$name] = $value;
            }
        }

        $headers['user-agent'] = 'hookrelay/1.0';
        $headers['x-hookrelay-event-id'] = $event->id;
        $headers['x-hookrelay-source'] = $event->source;
        $headers['x-hookrelay-attempt'] = (string) $attempt;

        if ($source?->forwardSecret !== null) {
            $headers['x-hookrelay-signature'] = HmacSignature::sign($event->body, $source->forwardSecret);
        }

        return $headers;
    }

    private static function excerpt(Response $response): ?string
    {
        $body = trim($response->body());

        return $body === '' ? null : Str::limit($body, 500);
    }

    private static function shortError(string $message): string
    {
        $message = preg_replace('/\s*\(see https?:\/\/\S+\)/', '', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;

        return Str::limit($message, 300);
    }
}
