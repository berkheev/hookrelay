<?php

declare(strict_types=1);

namespace App\Providers;

use App\Delivery\CircuitBreaker;
use App\Delivery\DeliveryProcessor;
use App\Delivery\RetryPolicy;
use App\Ingest\IngestService;
use App\Queue\DeliveryQueue;
use App\Queue\RabbitMq;
use App\Queue\RabbitMqDeliveryQueue;
use App\Sources\SourceRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

final class HookRelayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SourceRegistry::class, fn () => SourceRegistry::fromFile((string) config('hookrelay.sources_file')));
        $this->app->singleton(RabbitMq::class, fn () => new RabbitMq(config('hookrelay.rabbitmq')));
        $this->app->singleton(DeliveryQueue::class, RabbitMqDeliveryQueue::class);

        $this->app->singleton(RetryPolicy::class, fn () => RetryPolicy::fromConfig(config('hookrelay.retry')));

        $this->app->bind(CircuitBreaker::class, fn () => new CircuitBreaker(
            Cache::store(),
            (int) config('hookrelay.circuit_breaker.threshold'),
            (int) config('hookrelay.circuit_breaker.cooldown'),
        ));

        $this->app->bind(IngestService::class, fn (Application $app) => new IngestService(
            $app['db']->connection(),
            $app->make(DeliveryQueue::class),
            Cache::store(),
            $app->make(LoggerInterface::class),
            (int) config('hookrelay.idempotency_ttl'),
        ));

        $this->app->bind(DeliveryProcessor::class, fn (Application $app) => new DeliveryProcessor(
            $app->make(Http::class),
            $app->make(DeliveryQueue::class),
            $app->make(SourceRegistry::class),
            $app->make(RetryPolicy::class),
            $app->make(CircuitBreaker::class),
            $app->make(LoggerInterface::class),
            (float) config('hookrelay.http.timeout'),
            (float) config('hookrelay.http.connect_timeout'),
        ));
    }
}
