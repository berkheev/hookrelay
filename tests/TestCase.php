<?php

declare(strict_types=1);

namespace Tests;

use App\Queue\DeliveryQueue;
use App\Sources\Source;
use App\Sources\SourceRegistry;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Fakes\InMemoryDeliveryQueue;

abstract class TestCase extends BaseTestCase
{
    protected InMemoryDeliveryQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->queue = new InMemoryDeliveryQueue();
        $this->app->instance(DeliveryQueue::class, $this->queue);

        $this->app->instance(SourceRegistry::class, new SourceRegistry(
            new Source(
                name: 'github',
                destinations: ['https://ci.internal/hooks', 'https://chat.internal/github'],
                secret: 'topsecret',
                signatureHeader: 'X-Hub-Signature-256',
                idempotencyHeader: 'X-GitHub-Delivery',
            ),
            new Source(
                name: 'billing',
                destinations: ['https://ledger.internal/webhooks'],
                forwardSecret: 'downstream',
            ),
        ));
    }
}
