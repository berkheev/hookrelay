<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Queue\RabbitMq;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class HealthController
{
    public function __invoke(RabbitMq $rabbit): JsonResponse
    {
        $checks = [
            'postgres' => self::check(fn () => DB::select('select 1')),
            'redis' => self::check(fn () => Redis::connection()->ping()),
            'rabbitmq' => self::check(fn () => $rabbit->isHealthy() ?: throw new \RuntimeException('not connected')),
        ];

        $healthy = !in_array(false, $checks, true);

        return new JsonResponse([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => array_map(static fn (bool $ok) => $ok ? 'ok' : 'down', $checks),
        ], $healthy ? 200 : 503);
    }

    private static function check(\Closure $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
