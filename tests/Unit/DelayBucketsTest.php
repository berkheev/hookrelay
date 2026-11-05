<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Queue\DelayBuckets;
use App\Queue\RabbitMq;
use App\Queue\RabbitMqDeliveryQueue;
use PHPUnit\Framework\TestCase;

final class DelayBucketsTest extends TestCase
{
    public function test_rounds_up_to_the_next_bucket(): void
    {
        $this->assertSame(1, DelayBuckets::for(0));
        $this->assertSame(10, DelayBuckets::for(10));
        $this->assertSame(15, DelayBuckets::for(11));
        $this->assertSame(450, DelayBuckets::for(321));
    }

    public function test_caps_at_the_largest_bucket(): void
    {
        $this->assertSame(3600, DelayBuckets::for(86400));
    }

    public function test_rounding_never_exceeds_half_of_the_requested_delay(): void
    {
        foreach (range(10, 3600) as $seconds) {
            $this->assertLessThanOrEqual($seconds * 1.5, DelayBuckets::for($seconds), "delay {$seconds}s");
        }
    }

    public function test_queue_reports_the_delay_it_will_apply(): void
    {
        $queue = new RabbitMqDeliveryQueue(new RabbitMq([
            'host' => 'unused', 'port' => 5672, 'user' => 'x', 'password' => 'x',
            'vhost' => '/', 'exchange' => 'x', 'queue' => 'x', 'prefetch' => 1,
        ]));

        $this->assertSame(0, $queue->effectiveDelay(0));
        $this->assertSame(15, $queue->effectiveDelay(12));
    }

    public function test_queue_names(): void
    {
        $this->assertSame('hookrelay.deliveries.wait.30s', DelayBuckets::queueName('hookrelay.deliveries', 30));
    }
}
