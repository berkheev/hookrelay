<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Delivery\DeliveryProcessor;
use App\Queue\RabbitMq;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;

final class ConsumeCommand extends Command
{
    protected $signature = 'hookrelay:consume
        {--max-messages=0 : Exit after this many messages (0 = run forever)}';

    protected $description = 'Consume deliveries from RabbitMQ and forward them to their destinations';

    private bool $shouldStop = false;

    public function handle(RabbitMq $rabbit, DeliveryProcessor $processor): int
    {
        $this->trapSignals();

        $maxMessages = (int) $this->option('max-messages');
        $handled = 0;

        $channel = $rabbit->channel();
        $channel->basic_qos(0, $rabbit->prefetch(), false);

        $channel->basic_consume(
            queue: $rabbit->queue(),
            callback: function (AMQPMessage $message) use ($processor, &$handled, $maxMessages): void {
                $this->handleMessage($message, $processor);

                if ($maxMessages > 0 && ++$handled >= $maxMessages) {
                    $this->shouldStop = true;
                }
            },
        );

        $this->components->info(sprintf('Consuming %s (prefetch %d).', $rabbit->queue(), $rabbit->prefetch()));

        while ($channel->is_consuming() && !$this->shouldStop) {
            try {
                $channel->wait(null, false, 5);
            } catch (AMQPTimeoutException) {
            }
        }

        $rabbit->disconnect();
        $this->components->info('Consumer stopped.');

        return self::SUCCESS;
    }

    private function handleMessage(AMQPMessage $message, DeliveryProcessor $processor): void
    {
        $payload = json_decode($message->getBody(), true);
        $deliveryId = is_array($payload) && isset($payload['delivery_id']) ? (int) $payload['delivery_id'] : 0;

        if ($deliveryId <= 0) {
            $this->components->warn('Dropping malformed message: ' . $message->getBody());
            $message->reject(false);

            return;
        }

        try {
            $result = $processor->process($deliveryId);
            $message->ack();

            if ($this->output->isVerbose()) {
                $this->line(sprintf('#%d %s', $deliveryId, $result->value));
            }
        } catch (\Throwable $e) {
            report($e);
            $message->nack(true);
            DB::disconnect();
            sleep(1);
        }
    }

    private function trapSignals(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT, SIGQUIT] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->shouldStop = true;
            });
        }
    }
}
