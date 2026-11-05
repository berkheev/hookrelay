<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Message\AMQPMessage;

final class RabbitMqDeliveryQueue implements DeliveryQueue
{
    public function __construct(private readonly RabbitMq $rabbit)
    {
    }

    public function publish(int $deliveryId, int $delaySeconds = 0): void
    {
        $message = new AMQPMessage(
            json_encode(['delivery_id' => $deliveryId], JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ],
        );

        $send = function () use ($message, $delaySeconds): void {
            $channel = $this->rabbit->channel();

            if ($delaySeconds <= 0) {
                $channel->basic_publish($message, $this->rabbit->exchange(), RabbitMq::ROUTING_KEY);

                return;
            }

            $queue = DelayBuckets::queueName($this->rabbit->queue(), DelayBuckets::for($delaySeconds));
            $channel->basic_publish($message, '', $queue);
        };

        try {
            $send();
        } catch (\Throwable) {
            // The broker may have restarted since this Octane worker connected.
            $this->rabbit->disconnect();
            $send();
        }
    }

    public function effectiveDelay(int $seconds): int
    {
        return $seconds <= 0 ? 0 : DelayBuckets::for($seconds);
    }
}
