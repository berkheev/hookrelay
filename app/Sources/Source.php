<?php

declare(strict_types=1);

namespace App\Sources;

final readonly class Source
{
    /**
     * @param non-empty-list<string> $destinations
     */
    public function __construct(
        public string $name,
        public array $destinations,
        public ?string $secret = null,
        public string $signatureHeader = 'X-Signature-256',
        public ?string $idempotencyHeader = null,
        public ?string $forwardSecret = null,
    ) {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException(sprintf('Invalid source name "%s": use lowercase letters, digits, "-" and "_".', $name));
        }

        foreach ($destinations as $url) {
            if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                throw new \InvalidArgumentException(sprintf('Destination "%s" of source "%s" must be an http(s) URL.', $url, $name));
            }
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(string $name, array $config): self
    {
        $destinations = $config['destinations'] ?? [];
        if (!is_array($destinations) || $destinations === []) {
            throw new \InvalidArgumentException(sprintf('Source "%s" needs at least one destination.', $name));
        }

        $string = static fn (string $key): ?string => isset($config[$key]) && is_string($config[$key]) && $config[$key] !== ''
            ? $config[$key]
            : null;

        return new self(
            name: $name,
            destinations: array_values(array_map('strval', $destinations)),
            secret: $string('secret'),
            signatureHeader: $string('signature_header') ?? 'X-Signature-256',
            idempotencyHeader: $string('idempotency_header'),
            forwardSecret: $string('forward_secret'),
        );
    }

    public function requiresSignature(): bool
    {
        return $this->secret !== null;
    }
}
