<?php

declare(strict_types=1);

namespace App\Queue;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * exchange "hookrelay" --deliver--> "hookrelay.deliveries" (consumers)
 * "hookrelay.deliveries.wait.{N}s" (TTL N, no consumers) --dead-letter--> exchange
 */
final class RabbitMq
{
    public const ROUTING_KEY = 'deliver';

    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    /**
     * @param array{host: string, port: int, user: string, password: string, vhost: string, exchange: string, queue: string, prefetch: int} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public function channel(): AMQPChannel
    {
        if ($this->channel !== null && $this->channel->is_open() && $this->connection?->isConnected()) {
            return $this->channel;
        }

        $this->disconnect();

        $this->connection = new AMQPStreamConnection(
            $this->config['host'],
            $this->config['port'],
            $this->config['user'],
            $this->config['password'],
            $this->config['vhost'],
            connection_timeout: 3.0,
            read_write_timeout: 30.0,
            heartbeat: 15,
        );
        $this->channel = $this->connection->channel();
        $this->declareTopology($this->channel);

        return $this->channel;
    }

    public function exchange(): string
    {
        return $this->config['exchange'];
    }

    public function queue(): string
    {
        return $this->config['queue'];
    }

    public function prefetch(): int
    {
        return $this->config['prefetch'];
    }

    public function isHealthy(): bool
    {
        try {
            return $this->channel()->is_open();
        } catch (\Throwable) {
            return false;
        }
    }

    public function disconnect(): void
    {
        try {
            $this->channel?->close();
            $this->connection?->close();
        } catch (\Throwable) {
        }

        $this->channel = null;
        $this->connection = null;
    }

    private function declareTopology(AMQPChannel $channel): void
    {
        $exchange = $this->exchange();
        $queue = $this->queue();

        $channel->exchange_declare($exchange, 'direct', durable: true, auto_delete: false);

        $channel->queue_declare($queue, durable: true, auto_delete: false, arguments: new AMQPTable([
            'x-queue-type' => 'quorum',
        ]));
        $channel->queue_bind($queue, $exchange, self::ROUTING_KEY);

        foreach (DelayBuckets::BUCKETS as $seconds) {
            $channel->queue_declare(DelayBuckets::queueName($queue, $seconds), durable: true, auto_delete: false, arguments: new AMQPTable([
                'x-message-ttl' => $seconds * 1000,
                'x-dead-letter-exchange' => $exchange,
                'x-dead-letter-routing-key' => self::ROUTING_KEY,
            ]));
        }
    }
}
