<?php

return [

    'sources_file' => env('HOOKRELAY_SOURCES_FILE', base_path('sources.json')),

    'max_body_bytes' => (int) env('HOOKRELAY_MAX_BODY_BYTES', 1024 * 1024),

    'rate_limit_per_minute' => (int) env('HOOKRELAY_RATE_LIMIT', 600),

    'idempotency_ttl' => (int) env('HOOKRELAY_IDEMPOTENCY_TTL', 86400),

    'retention_days' => (int) env('HOOKRELAY_RETENTION_DAYS', 14),

    'retry' => [
        'max_attempts' => (int) env('HOOKRELAY_MAX_ATTEMPTS', 8),
        'base_delay' => (int) env('HOOKRELAY_BASE_DELAY', 10),
        'max_delay' => (int) env('HOOKRELAY_MAX_DELAY', 3600),
        'jitter' => 0.2,
    ],

    'http' => [
        'timeout' => (float) env('HOOKRELAY_HTTP_TIMEOUT', 10),
        'connect_timeout' => (float) env('HOOKRELAY_HTTP_CONNECT_TIMEOUT', 5),
    ],

    'circuit_breaker' => [
        'threshold' => (int) env('HOOKRELAY_CB_THRESHOLD', 5),
        'cooldown' => (int) env('HOOKRELAY_CB_COOLDOWN', 60),
    ],

    'rabbitmq' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'exchange' => env('RABBITMQ_EXCHANGE', 'hookrelay'),
        'queue' => env('RABBITMQ_QUEUE', 'hookrelay.deliveries'),
        'prefetch' => (int) env('RABBITMQ_PREFETCH', 10),
    ],

    // Empty password disables auth for the dashboard and /api.
    'admin' => [
        'user' => env('HOOKRELAY_ADMIN_USER', 'admin'),
        'password' => env('HOOKRELAY_ADMIN_PASSWORD'),
    ],

];
