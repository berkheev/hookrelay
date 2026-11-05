@extends('layouts.app')

@section('title', 'Events')

@section('content')
    <div class="grid">
        <div class="card"><div class="label">Events · 24h</div><div class="value">{{ number_format($stats['events_24h']) }}</div></div>
        <div class="card"><div class="label">Delivered</div><div class="value ok">{{ number_format($stats['delivered']) }}</div></div>
        <div class="card"><div class="label">In queue</div><div class="value">{{ number_format($stats['pending']) }}</div></div>
        <div class="card"><div class="label">Failed</div><div class="value bad">{{ number_format($stats['failed']) }}</div></div>
        <div class="card"><div class="label">Success rate</div><div class="value">{{ $stats['success_rate'] !== null ? $stats['success_rate'].'%' : '—' }}</div></div>
        <div class="card"><div class="label">p95 latency</div><div class="value">{{ $stats['p95_ms'] !== null ? $stats['p95_ms'].' ms' : '—' }}</div></div>
    </div>

    <div class="filters">
        <a href="{{ route('dashboard', array_filter(['status' => $filters['status']])) }}" class="{{ $filters['source'] === null ? 'active' : '' }}">All sources</a>
        @foreach ($sources as $name)
            <a href="{{ route('dashboard', array_filter(['source' => $name, 'status' => $filters['status']])) }}" class="{{ $filters['source'] === $name ? 'active' : '' }}">{{ $name }}</a>
        @endforeach
        <span class="muted" style="padding: 4px 6px">|</span>
        @foreach (['pending' => 'Retrying', 'failed' => 'Failed'] as $value => $label)
            <a href="{{ route('dashboard', array_filter(['source' => $filters['source'], 'status' => $filters['status'] === $value ? null : $value])) }}" class="{{ $filters['status'] === $value ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="panel">
        <table>
            <thead>
            <tr>
                <th style="width: 150px">Received</th>
                <th style="width: 120px">Source</th>
                <th>Event</th>
                <th>Deliveries</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($events as $event)
                <tr>
                    <td class="muted" title="{{ $event->received_at->toIso8601String() }}">{{ $event->received_at->diffForHumans() }}</td>
                    <td><span class="tag">{{ $event->source }}</span></td>
                    <td>
                        <a href="{{ route('events.show', $event) }}">{{ $event->type ?? 'event' }}</a>
                        <div class="muted mono">{{ \Illuminate\Support\Str::limit($event->id, 13, '…') }}</div>
                    </td>
                    <td>
                        <div class="deliveries">
                            @foreach ($event->deliveries as $delivery)
                                <div>
                                    <span class="pill {{ $delivery->status->value }}">{{ $delivery->isRetrying() ? 'Retrying' : $delivery->status->label() }}</span>
                                    <span class="mono">{{ $delivery->destinationHost() }}{{ parse_url($delivery->destination, PHP_URL_PATH) }}</span>
                                    <span class="muted">
                                        · {{ $delivery->attempts }} {{ \Illuminate\Support\Str::plural('attempt', $delivery->attempts) }}
                                        @if ($delivery->last_status_code) · HTTP {{ $delivery->last_status_code }} @endif
                                        @if ($delivery->status === \App\Enums\DeliveryStatus::Pending && $delivery->next_attempt_at?->isFuture())
                                            · next {{ $delivery->next_attempt_at->diffForHumans() }}
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted" style="text-align:center; padding: 40px">No events yet. Send one to <code>POST /hooks/{source}</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="pagination">
        @if ($events->previousPageUrl()) <a href="{{ $events->previousPageUrl() }}">← Newer</a> @endif
        @if ($events->nextPageUrl()) <a href="{{ $events->nextPageUrl() }}">Older →</a> @endif
    </div>
@endsection
