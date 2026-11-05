<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Delivery\ReplayService;
use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Event;
use App\Sources\SourceRegistry;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardController
{
    public function index(Request $request, SourceRegistry $sources, Cache $cache): View
    {
        $source = $request->string('source')->toString() ?: null;
        $status = DeliveryStatus::tryFrom($request->string('status')->toString());

        $events = Event::query()
            ->with('deliveries')
            ->when($source, fn ($q) => $q->where('source', $source))
            ->when($status, fn ($q) => $q->whereHas('deliveries', fn ($d) => $d->where('status', $status)))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $stats = $cache->remember('hookrelay:stats', 10, fn () => $this->stats());

        return view('dashboard.index', [
            'events' => $events,
            'stats' => $stats,
            'sources' => array_keys($sources->all()),
            'filters' => ['source' => $source, 'status' => $status?->value],
        ]);
    }

    public function show(Event $event): View
    {
        return view('dashboard.show', [
            'event' => $event->load('deliveries.attemptLog'),
        ]);
    }

    public function replay(Delivery $delivery, ReplayService $replay): RedirectResponse
    {
        $replay->replay($delivery);

        return back()->with('status', sprintf('Delivery #%d to %s queued for replay.', $delivery->id, $delivery->destinationHost()));
    }

    /**
     * @return array{events_24h: int, delivered: int, pending: int, failed: int, success_rate: float|null, p95_ms: int|null}
     */
    private function stats(): array
    {
        $since = Carbon::now()->subDay();

        $byStatus = Delivery::query()
            ->where('created_at', '>=', $since)
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status');

        $delivered = (int) ($byStatus[DeliveryStatus::Delivered->value] ?? 0);
        $failed = (int) ($byStatus[DeliveryStatus::Failed->value] ?? 0);
        $finished = $delivered + $failed;

        $p95 = DB::table('delivery_attempts')
            ->where('created_at', '>=', $since)
            ->selectRaw('percentile_cont(0.95) within group (order by duration_ms) as p95')
            ->value('p95');

        return [
            'events_24h' => Event::query()->where('received_at', '>=', $since)->count(),
            'delivered' => $delivered,
            'pending' => (int) ($byStatus[DeliveryStatus::Pending->value] ?? 0),
            'failed' => $failed,
            'success_rate' => $finished > 0 ? round($delivered / $finished * 100, 1) : null,
            'p95_ms' => $p95 === null ? null : (int) round((float) $p95),
        ];
    }
}
