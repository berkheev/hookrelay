<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Queue\DeliveryQueue;
use Illuminate\Support\Carbon;

final class ReplayService
{
    public function __construct(private readonly DeliveryQueue $queue)
    {
    }

    public function replay(Delivery $delivery): Delivery
    {
        if ($delivery->locked_until !== null && $delivery->locked_until->isFuture()) {
            throw new DeliveryInFlightException($delivery);
        }

        $delivery->fill([
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'next_attempt_at' => Carbon::now(),
            'locked_until' => null,
            'delivered_at' => null,
        ])->save();

        $this->queue->publish($delivery->id);

        return $delivery;
    }
}
