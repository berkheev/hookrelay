<?php

declare(strict_types=1);

namespace App\Queue;

interface DeliveryQueue
{
    public function publish(int $deliveryId, int $delaySeconds = 0): void;

    /**
     * The delay the queue will actually apply, which may be rounded.
     */
    public function effectiveDelay(int $seconds): int;
}
