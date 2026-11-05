@extends('layouts.app')

@section('title', $event->type ?? 'Event')

@section('content')
    <p class="muted"><a href="{{ route('dashboard') }}">← Events</a></p>
    <h1>
        <span class="tag">{{ $event->source }}</span>
        {{ $event->type ?? 'event' }}
    </h1>
    <p class="muted mono">
        {{ $event->id }} · received {{ $event->received_at->toDayDateTimeString() }} UTC
        @if ($event->idempotency_key) · key {{ $event->idempotency_key }} @endif
    </p>

    <h2>Deliveries</h2>
    <div class="panel">
        <table>
            <thead>
            <tr><th>Destination</th><th style="width: 120px">Status</th><th>Attempts</th><th style="width: 100px"></th></tr>
            </thead>
            <tbody>
            @foreach ($event->deliveries as $delivery)
                <tr>
                    <td class="mono">{{ $delivery->destination }}</td>
                    <td><span class="pill {{ $delivery->status->value }}">{{ $delivery->status->label() }}</span></td>
                    <td>
                        @if ($delivery->attemptLog->isEmpty())
                            <span class="muted">
                                Not attempted yet
                                @if ($delivery->next_attempt_at?->isFuture()) · destination unavailable, next try {{ $delivery->next_attempt_at->diffForHumans() }} @endif
                            </span>
                        @else
                            <ul class="timeline">
                                @foreach ($delivery->attemptLog as $attempt)
                                    <li>
                                        <span class="muted">#{{ $attempt->number }}</span>
                                        <span class="{{ $attempt->succeeded() ? 'ok' : 'bad' }} mono">{{ $attempt->status_code ? 'HTTP '.$attempt->status_code : 'error' }}</span>
                                        <span class="muted">{{ $attempt->error ?? \Illuminate\Support\Str::limit((string) $attempt->response_excerpt, 80) }}</span>
                                        <span class="muted mono">{{ $attempt->duration_ms }} ms · {{ $attempt->created_at->format('H:i:s') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if ($delivery->status === \App\Enums\DeliveryStatus::Pending && $delivery->next_attempt_at?->isFuture())
                                <div class="muted" style="margin-top: 6px">Next attempt {{ $delivery->next_attempt_at->diffForHumans() }}</div>
                            @endif
                        @endif
                    </td>
                    <td>
                        <form method="post" action="{{ route('deliveries.replay', $delivery) }}">
                            @csrf
                            <button type="submit">Replay</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="two">
        <div>
            <h2>Headers</h2>
            <pre>@foreach ($event->headers as $name => $value){{ $name }}: {{ $value }}
@endforeach</pre>
        </div>
        <div>
            <h2>Body</h2>
            <pre>{{ $event->prettyBody() }}</pre>
        </div>
    </div>
@endsection
