<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Event;
use App\Support\HmacSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class IngestWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '{"ref":"refs/heads/main","repository":{"full_name":"acme/shop"}}';

    public function test_signed_webhook_is_stored_and_fanned_out(): void
    {
        $response = $this->sendGithub(self::PAYLOAD, delivery: 'abc-1');

        $response->assertStatus(202)->assertJson(['duplicate' => false, 'deliveries' => 2]);

        $event = Event::query()->sole();
        $this->assertSame('github', $event->source);
        $this->assertSame('push', $event->type);
        $this->assertSame('abc-1', $event->idempotency_key);
        $this->assertSame(self::PAYLOAD, $event->body);
        $this->assertSame('push', $event->header('X-GitHub-Event'));

        $deliveries = Delivery::query()->orderBy('id')->get();
        $this->assertSame(['https://ci.internal/hooks', 'https://chat.internal/github'], $deliveries->pluck('destination')->all());
        $this->assertTrue($deliveries->every(fn (Delivery $d) => $d->status === DeliveryStatus::Pending));

        $this->assertSame($deliveries->pluck('id')->all(), $this->queue->ids());
    }

    public function test_unknown_source_is_404(): void
    {
        $this->postRaw('/hooks/nope', '{}')->assertNotFound()->assertJson(['error' => 'unknown_source']);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $this->postRaw('/hooks/github', self::PAYLOAD, ['X-Hub-Signature-256' => 'sha256=' . str_repeat('0', 64)])
            ->assertUnauthorized()
            ->assertJson(['error' => 'invalid_signature']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_missing_signature_is_rejected(): void
    {
        $this->postRaw('/hooks/github', self::PAYLOAD)->assertUnauthorized();
    }

    public function test_tampered_body_is_rejected(): void
    {
        $this->postRaw('/hooks/github', self::PAYLOAD . ' ', [
            'X-Hub-Signature-256' => HmacSignature::sign(self::PAYLOAD, 'topsecret'),
        ])->assertUnauthorized();
    }

    public function test_source_without_secret_accepts_unsigned_hooks(): void
    {
        $this->postRaw('/hooks/billing', '{"type":"invoice.paid"}')->assertStatus(202);

        $this->assertSame('invoice.paid', Event::query()->sole()->type);
    }

    public function test_redelivery_with_same_key_is_deduplicated(): void
    {
        $first = $this->sendGithub(self::PAYLOAD, delivery: 'same-key')->assertStatus(202);
        $second = $this->sendGithub(self::PAYLOAD, delivery: 'same-key')->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertTrue($second->json('duplicate'));
        $this->assertDatabaseCount('events', 1);
        $this->assertDatabaseCount('deliveries', 2);
    }

    public function test_dedup_falls_back_to_database_when_cache_was_flushed(): void
    {
        $this->sendGithub(self::PAYLOAD, delivery: 'k')->assertStatus(202);
        cache()->flush();

        $this->sendGithub(self::PAYLOAD, delivery: 'k')->assertOk()->assertJson(['duplicate' => true]);
        $this->assertDatabaseCount('events', 1);
    }

    public function test_oversized_payload_is_413(): void
    {
        config(['hookrelay.max_body_bytes' => 10]);

        $this->postRaw('/hooks/billing', str_repeat('x', 11))->assertStatus(413);
    }

    public function test_rate_limit_per_source(): void
    {
        config(['hookrelay.rate_limit_per_minute' => 2]);

        $this->postRaw('/hooks/billing', '{}')->assertStatus(202);
        $this->postRaw('/hooks/billing', '{}')->assertStatus(202);
        $this->postRaw('/hooks/billing', '{}')->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_webhook_is_accepted_even_if_broker_is_down(): void
    {
        $this->queue->failing = true;

        $this->postRaw('/hooks/billing', '{}')->assertStatus(202);

        // Stored and pending: the sweeper will publish it later.
        $this->assertSame(DeliveryStatus::Pending, Delivery::query()->sole()->status);
    }

    public function test_sensitive_headers_are_not_stored(): void
    {
        $this->postRaw('/hooks/billing', '{}', ['Authorization' => 'Bearer x', 'Cookie' => 'a=b'])->assertStatus(202);

        $headers = Event::query()->sole()->headers;
        $this->assertArrayNotHasKey('authorization', $headers);
        $this->assertArrayNotHasKey('cookie', $headers);
    }

    private function sendGithub(string $payload, string $delivery): TestResponse
    {
        return $this->postRaw('/hooks/github', $payload, [
            'X-GitHub-Event' => 'push',
            'X-GitHub-Delivery' => $delivery,
            'X-Hub-Signature-256' => HmacSignature::sign($payload, 'topsecret'),
        ]);
    }

    /**
     * @param array<string, string> $headers
     */
    private function postRaw(string $uri, string $body, array $headers = []): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        foreach ($headers as $name => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri, [], [], [], $server, $body);
    }
}
