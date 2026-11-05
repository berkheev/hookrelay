<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Delivery\CircuitBreaker;
use App\Delivery\DeliveryProcessor;
use App\Delivery\ProcessResult;
use App\Delivery\RetryPolicy;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Event;
use App\Support\HmacSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class DeliveryProcessorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-05 12:00:00');
        // Deterministic backoff: no jitter.
        $this->app->instance(RetryPolicy::class, new RetryPolicy(maxAttempts: 3, baseDelay: 10, maxDelay: 3600, jitter: 0.0));
    }

    public function test_successful_delivery(): void
    {
        Http::fake(['*' => Http::response('{"ok":true}', 200)]);
        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->assertSame(ProcessResult::Delivered, $this->processor()->process($delivery->id));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(200, $delivery->last_status_code);
        $this->assertNotNull($delivery->delivered_at);
        $this->assertNull($delivery->locked_until);

        $attempt = $delivery->attemptLog()->sole();
        $this->assertSame(1, $attempt->number);
        $this->assertSame('{"ok":true}', $attempt->response_excerpt);
    }

    public function test_forwards_body_and_provider_headers_but_not_the_original_signature(): void
    {
        Http::fake(['*' => Http::response('', 204)]);
        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->processor()->process($delivery->id);

        Http::assertSent(function (Request $request) use ($delivery) {
            return $request->url() === 'https://ci.internal/hooks'
                && $request->body() === '{"ref":"main"}'
                && $request->header('X-GitHub-Event') === ['push']
                && $request->header('X-Hub-Signature-256') === []
                && $request->header('X-Hookrelay-Event-Id') === [$delivery->event_id]
                && $request->header('X-Hookrelay-Attempt') === ['1'];
        });
    }

    public function test_signs_forwarded_payload_when_source_has_forward_secret(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $delivery = $this->delivery('billing', 'https://ledger.internal/webhooks');

        $this->processor()->process($delivery->id);

        Http::assertSent(fn (Request $request) => $request->header('X-Hookrelay-Signature') === [HmacSignature::sign('{"ref":"main"}', 'downstream')]);
    }

    public function test_server_error_is_retried_with_backoff(): void
    {
        Http::fake(['*' => Http::response('Bad gateway', 502)]);
        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->assertSame(ProcessResult::Retrying, $this->processor()->process($delivery->id));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame(502, $delivery->last_status_code);
        $this->assertSame('2026-11-05 12:00:10', $delivery->next_attempt_at?->format('Y-m-d H:i:s'));
        $this->assertSame([['id' => $delivery->id, 'delay' => 10]], $this->queue->published);
    }

    public function test_connection_error_is_retried(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect (see https://curl.haxx.se/libcurl/c/libcurl-errors.html)'));
        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->assertSame(ProcessResult::Retrying, $this->processor()->process($delivery->id));

        $delivery->refresh();
        $this->assertNull($delivery->last_status_code);
        $this->assertSame('cURL error 7: Failed to connect', $delivery->last_error);
    }

    public function test_client_error_fails_without_retry(): void
    {
        Http::fake(['*' => Http::response('{"error":"unknown repo"}', 422)]);
        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->assertSame(ProcessResult::Failed, $this->processor()->process($delivery->id));

        $this->assertSame(DeliveryStatus::Failed, $delivery->refresh()->status);
        $this->assertSame([], $this->queue->published);
    }

    public function test_rate_limited_response_is_retried(): void
    {
        Http::fake(['*' => Http::response('', 429)]);

        $this->assertSame(ProcessResult::Retrying, $this->processor()->process($this->delivery('github', 'https://ci.internal/hooks')->id));
    }

    public function test_gives_up_after_max_attempts(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $delivery = $this->delivery('github', 'https://ci.internal/hooks', attempts: 2);

        $this->assertSame(ProcessResult::Failed, $this->processor()->process($delivery->id));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Failed, $delivery->status);
        $this->assertSame(3, $delivery->attempts);
    }

    public function test_duplicate_or_stale_messages_are_skipped(): void
    {
        Http::fake();

        $delivered = $this->delivery('github', 'https://ci.internal/hooks', status: DeliveryStatus::Delivered);
        $notDue = $this->delivery('github', 'https://ci.internal/hooks', nextAttemptAt: Carbon::now()->addMinute());
        $claimed = $this->delivery('github', 'https://ci.internal/hooks', lockedUntil: Carbon::now()->addSeconds(20));

        foreach ([$delivered, $notDue, $claimed] as $delivery) {
            $this->assertSame(ProcessResult::Skipped, $this->processor()->process($delivery->id));
        }
        $this->assertSame(ProcessResult::Skipped, $this->processor()->process(999_999));

        Http::assertNothingSent();
    }

    public function test_open_circuit_postpones_without_spending_an_attempt(): void
    {
        Http::fake();
        $breaker = $this->app->make(CircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure('ci.internal');
        }

        $delivery = $this->delivery('github', 'https://ci.internal/hooks');

        $this->assertSame(ProcessResult::Postponed, $this->processor()->process($delivery->id));

        $this->assertSame(0, $delivery->refresh()->attempts);
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertCount(1, $this->queue->published);
        Http::assertNothingSent();
    }

    public function test_repeated_failures_open_the_circuit(): void
    {
        Http::fake(['*' => Http::response('', 503)]);
        config(['hookrelay.circuit_breaker.threshold' => 2]);

        $this->processor()->process($this->delivery('github', 'https://ci.internal/a')->id);
        $this->processor()->process($this->delivery('github', 'https://ci.internal/b')->id);

        $this->assertGreaterThan(0, $this->app->make(CircuitBreaker::class)->openFor('ci.internal'));
    }

    private function processor(): DeliveryProcessor
    {
        return $this->app->make(DeliveryProcessor::class);
    }

    private function delivery(
        string $source,
        string $destination,
        int $attempts = 0,
        DeliveryStatus $status = DeliveryStatus::Pending,
        ?Carbon $nextAttemptAt = null,
        ?Carbon $lockedUntil = null,
    ): Delivery {
        $event = Event::create([
            'source' => $source,
            'headers' => [
                'content-type' => 'application/json',
                'x-github-event' => 'push',
                'x-hub-signature-256' => 'sha256=original',
            ],
            'body' => '{"ref":"main"}',
            'type' => 'push',
            'received_at' => Carbon::now(),
        ]);

        return $event->deliveries()->create([
            'destination' => $destination,
            'status' => $status,
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttemptAt ?? Carbon::now(),
            'locked_until' => $lockedUntil,
        ]);
    }
}
