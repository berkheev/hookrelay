<?php

declare(strict_types=1);

namespace App\Delivery;

final class RetryPolicy
{
    /** @var \Closure(): float */
    private \Closure $random;

    public function __construct(
        public readonly int $maxAttempts = 8,
        public readonly int $baseDelay = 10,
        public readonly int $maxDelay = 3600,
        public readonly float $jitter = 0.2,
        ?\Closure $random = null,
    ) {
        if ($maxAttempts < 1 || $baseDelay < 1 || $maxDelay < $baseDelay || $jitter < 0 || $jitter >= 1) {
            throw new \InvalidArgumentException('Invalid retry policy.');
        }

        $this->random = $random ?? static fn (): float => mt_rand() / (mt_getrandmax() + 1);
    }

    /**
     * @param array{max_attempts: int, base_delay: int, max_delay: int, jitter: float} $config
     */
    public static function fromConfig(array $config): self
    {
        return new self($config['max_attempts'], $config['base_delay'], $config['max_delay'], $config['jitter']);
    }

    public function canRetry(int $attemptsMade): bool
    {
        return $attemptsMade < $this->maxAttempts;
    }

    public function delay(int $attemptsMade): int
    {
        $exponent = min(max(0, $attemptsMade - 1), 30);
        $delay = min($this->maxDelay, $this->baseDelay * (2 ** $exponent));

        $spread = $delay * $this->jitter;
        $delay += ($this->random)() * 2 * $spread - $spread;

        return max(1, (int) round($delay));
    }

    public function isRetryable(?int $statusCode): bool
    {
        return $statusCode === null
            || $statusCode >= 500
            || in_array($statusCode, [408, 425, 429], true);
    }
}
