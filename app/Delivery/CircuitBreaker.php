<?php

declare(strict_types=1);

namespace App\Delivery;

use Illuminate\Contracts\Cache\Repository as Cache;

final class CircuitBreaker
{
    private const PREFIX = 'hookrelay:circuit:';

    public function __construct(
        private readonly Cache $cache,
        private readonly int $threshold = 5,
        private readonly int $cooldown = 60,
    ) {
    }

    public function openFor(string $host): int
    {
        $until = $this->cache->get(self::PREFIX . 'open:' . $host);

        return is_numeric($until) ? max(0, (int) $until - time()) : 0;
    }

    public function recordSuccess(string $host): void
    {
        $this->cache->forget(self::PREFIX . 'failures:' . $host);
        $this->cache->forget(self::PREFIX . 'open:' . $host);
    }

    public function recordFailure(string $host): void
    {
        $key = self::PREFIX . 'failures:' . $host;

        $this->cache->add($key, 0, $this->cooldown * 10);
        $failures = (int) $this->cache->increment($key);

        if ($failures >= $this->threshold) {
            $this->cache->put(self::PREFIX . 'open:' . $host, time() + $this->cooldown, $this->cooldown);
            $this->cache->forget($key);
        }
    }
}
