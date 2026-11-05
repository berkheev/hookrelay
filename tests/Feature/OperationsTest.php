<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class OperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_replay_is_refused_while_delivery_is_in_flight(): void
    {
        $delivery = $this->makeDelivery(lockedUntil: Carbon::now()->addSeconds(20));

        $this->postJson("/api/deliveries/{$delivery->id}/replay")
            ->assertStatus(409)
            ->assertJson(['error' => 'delivery_in_flight']);

        $this->assertSame([], $this->queue->ids());
    }

    public function test_replay_requeues_a_failed_delivery_with_fresh_budget(): void
    {
        $delivery = $this->makeDelivery(status: DeliveryStatus::Failed, attempts: 8);

        $this->postJson("/api/deliveries/{$delivery->id}/replay")
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.attempts', 0);

        $this->assertSame([$delivery->id], $this->queue->ids());
    }

    public function test_dashboard_replay_button(): void
    {
        $delivery = $this->makeDelivery(status: DeliveryStatus::Failed);

        $this->from('/events/' . $delivery->event_id)
            ->post("/deliveries/{$delivery->id}/replay")
            ->assertRedirect('/events/' . $delivery->event_id)
            ->assertSessionHas('status');

        $this->assertSame(DeliveryStatus::Pending, $delivery->refresh()->status);
    }

    public function test_sweeper_requeues_only_overdue_unclaimed_deliveries(): void
    {
        Carbon::setTestNow('2026-11-05 12:00:00');

        $overdue = $this->makeDelivery(nextAttemptAt: Carbon::now()->subMinutes(5));
        $this->makeDelivery(nextAttemptAt: Carbon::now()->subSeconds(10));                                    // within grace
        $this->makeDelivery(nextAttemptAt: Carbon::now()->subMinutes(5), lockedUntil: Carbon::now()->addMinute()); // in flight
        $this->makeDelivery(status: DeliveryStatus::Delivered, nextAttemptAt: Carbon::now()->subHour());

        $this->artisan('hookrelay:sweep')->assertSuccessful();

        $this->assertSame([$overdue->id], $this->queue->ids());
    }

    public function test_prune_removes_old_events_with_their_deliveries(): void
    {
        $old = $this->makeDelivery(receivedAt: Carbon::now()->subDays(30));
        $fresh = $this->makeDelivery();

        $this->artisan('hookrelay:prune', ['--days' => 14])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($fresh);
    }

    public function test_api_lists_and_filters_events(): void
    {
        $this->makeDelivery(status: DeliveryStatus::Failed, source: 'github');
        $this->makeDelivery(status: DeliveryStatus::Delivered, source: 'billing');

        $this->getJson('/api/events')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/events?status=failed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.source', 'github');
        $this->getJson('/api/events?status=bogus')->assertUnprocessable();
    }

    public function test_api_shows_event_with_attempts(): void
    {
        $delivery = $this->makeDelivery();
        $delivery->attemptLog()->create(['number' => 1, 'status_code' => 500, 'duration_ms' => 42, 'created_at' => Carbon::now()]);

        $this->getJson("/api/events/{$delivery->event_id}")
            ->assertOk()
            ->assertJsonPath('data.body', '{"hello":"world"}')
            ->assertJsonPath('data.deliveries.0.attempt_log.0.status_code', 500);
    }

    public function test_admin_auth_when_password_is_configured(): void
    {
        config(['hookrelay.admin.user' => 'admin', 'hookrelay.admin.password' => 's3cret']);

        $this->get('/')->assertUnauthorized()->assertHeader('WWW-Authenticate');
        $this->getJson('/api/events')->assertUnauthorized();

        $this->withBasicAuth('admin', 's3cret')->get('/')->assertOk();
    }

    public function test_dashboard_renders(): void
    {
        $delivery = $this->makeDelivery(status: DeliveryStatus::Failed);

        $this->get('/')->assertOk()->assertSee('hookrelay')->assertSee('Failed');
        $this->get('/?source=github&status=failed')->assertOk();
        $this->get('/events/' . $delivery->event_id)->assertOk()->assertSee('&quot;hello&quot;', false);
    }

    private function makeDelivery(
        DeliveryStatus $status = DeliveryStatus::Pending,
        int $attempts = 0,
        ?Carbon $nextAttemptAt = null,
        ?Carbon $lockedUntil = null,
        ?Carbon $receivedAt = null,
        string $source = 'github',
    ): Delivery {
        $event = Event::create([
            'source' => $source,
            'headers' => ['content-type' => 'application/json'],
            'body' => '{"hello":"world"}',
            'received_at' => $receivedAt ?? Carbon::now(),
        ]);

        return $event->deliveries()->create([
            'destination' => 'https://ci.internal/hooks',
            'status' => $status,
            'attempts' => $attempts,
            'next_attempt_at' => $nextAttemptAt ?? Carbon::now(),
            'locked_until' => $lockedUntil,
        ]);
    }
}
