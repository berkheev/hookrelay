<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Queue\DeliveryQueue;

final class InMemoryDeliveryQueue implements DeliveryQueue
{
    /** @var list<array{id: int, delay: int}> */
    public array $published = [];

    public bool $failing = false;

    public function publish(int $deliveryId, int $delaySeconds = 0): void
    {
        if ($this->failing) {
            throw new \RuntimeException('Broker unavailable');
        }

        $this->published[] = ['id' => $deliveryId, 'delay' => $delaySeconds];
    }

    public function effectiveDelay(int $seconds): int
    {
        return max(0, $seconds);
    }

    /**
     * @return list<int>
     */
    public function ids(): array
    {
        return array_column($this->published, 'id');
    }
}
