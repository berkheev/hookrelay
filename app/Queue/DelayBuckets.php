<?php

declare(strict_types=1);

namespace App\Queue;

/**
 * RabbitMQ only expires messages at the head of a queue, so a single wait queue
 * with per-message TTLs would let a long delay block a short one. Instead each
 * delay gets its own wait queue and requested delays are rounded up to a bucket.
 */
final class DelayBuckets
{
    public const BUCKETS = [1, 2, 5, 10, 15, 20, 30, 45, 60, 90, 120, 180, 240, 300, 450, 600, 900, 1200, 1800, 2700, 3600];

    public static function for(int $seconds): int
    {
        foreach (self::BUCKETS as $bucket) {
            if ($seconds <= $bucket) {
                return $bucket;
            }
        }

        return self::BUCKETS[array_key_last(self::BUCKETS)];
    }

    public static function queueName(string $baseQueue, int $bucket): string
    {
        return sprintf('%s.wait.%ds', $baseQueue, $bucket);
    }
}
