<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Delivery\RetryPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryPolicyTest extends TestCase
{
    public function test_delay_grows_exponentially_and_is_capped(): void
    {
        $policy = new RetryPolicy(maxAttempts: 20, baseDelay: 10, maxDelay: 300, jitter: 0.0);

        $this->assertSame([10, 20, 40, 80, 160, 300, 300], array_map($policy->delay(...), [1, 2, 3, 4, 5, 6, 7]));
    }

    public function test_jitter_stays_within_bounds(): void
    {
        $low = new RetryPolicy(baseDelay: 100, jitter: 0.2, random: static fn (): float => 0.0);
        $high = new RetryPolicy(baseDelay: 100, jitter: 0.2, random: static fn (): float => 0.999999);

        $this->assertSame(80, $low->delay(1));
        $this->assertSame(120, $high->delay(1));
    }

    public function test_retry_budget(): void
    {
        $policy = new RetryPolicy(maxAttempts: 3);

        $this->assertTrue($policy->canRetry(2));
        $this->assertFalse($policy->canRetry(3));
    }

    #[DataProvider('statuses')]
    public function test_retryable_statuses(?int $status, bool $retryable): void
    {
        $this->assertSame($retryable, (new RetryPolicy())->isRetryable($status));
    }

    /**
     * @return iterable<string, array{int|null, bool}>
     */
    public static function statuses(): iterable
    {
        yield 'network error' => [null, true];
        yield 'timeout' => [408, true];
        yield 'too many requests' => [429, true];
        yield 'server error' => [500, true];
        yield 'bad gateway' => [502, true];
        yield 'bad request' => [400, false];
        yield 'unauthorized' => [401, false];
        yield 'not found' => [404, false];
        yield 'unprocessable' => [422, false];
    }

    public function test_rejects_nonsense_configuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RetryPolicy(baseDelay: 100, maxDelay: 10);
    }
}
