<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Models\Event;
use App\Queue\DeliveryQueue;
use App\Sources\Source;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Psr\Log\LoggerInterface;

final class IngestService
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly DeliveryQueue $queue,
        private readonly Cache $cache,
        private readonly LoggerInterface $logger,
        private readonly int $idempotencyTtl = 86400,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function ingest(Source $source, array $headers, string $body): IngestResult
    {
        $key = $this->idempotencyKey($source, $headers);

        if ($key !== null && !$this->cache->add($this->lockKey($source, $key), 1, $this->idempotencyTtl)) {
            $existing = $this->findByKey($source, $key);
            if ($existing !== null) {
                return IngestResult::duplicate($existing);
            }
        }

        try {
            $event = $this->db->transaction(fn (): Event => $this->store($source, $headers, $body, $key));
        } catch (UniqueConstraintViolationException $e) {
            $existing = $key !== null ? $this->findByKey($source, $key) : null;

            return $existing !== null ? IngestResult::duplicate($existing) : throw $e;
        }

        $deliveryIds = $event->deliveries()->pluck('id')->all();

        // Publishing happens after commit; if the broker is down the sweeper picks these up.
        foreach ($deliveryIds as $id) {
            try {
                $this->queue->publish((int) $id);
            } catch (\Throwable $e) {
                $this->logger->error('Could not publish delivery', ['delivery_id' => $id, 'exception' => $e->getMessage()]);
            }
        }

        return IngestResult::accepted($event, count($deliveryIds));
    }

    /**
     * @param array<string, string> $headers
     */
    private function store(Source $source, array $headers, string $body, ?string $key): Event
    {
        $now = Carbon::now();

        $event = Event::create([
            'source' => $source->name,
            'idempotency_key' => $key,
            'type' => Event::detectType($headers, $body),
            'headers' => $headers,
            'body' => $body,
            'received_at' => $now,
        ]);

        foreach ($source->destinations as $destination) {
            $event->deliveries()->create([
                'destination' => $destination,
                'next_attempt_at' => $now,
            ]);
        }

        return $event;
    }

    /**
     * @param array<string, string> $headers
     */
    private function idempotencyKey(Source $source, array $headers): ?string
    {
        if ($source->idempotencyHeader === null) {
            return null;
        }

        $key = $headers[strtolower($source->idempotencyHeader)] ?? '';

        return $key === '' ? null : mb_substr($key, 0, 255);
    }

    private function lockKey(Source $source, string $key): string
    {
        return sprintf('hookrelay:idem:%s:%s', $source->name, sha1($key));
    }

    private function findByKey(Source $source, string $key): ?Event
    {
        return Event::query()
            ->where('source', $source->name)
            ->where('idempotency_key', $key)
            ->first();
    }
}
