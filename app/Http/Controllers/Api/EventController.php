<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\DeliveryStatus;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

final class EventController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'source' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'type' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $events = Event::query()
            ->with('deliveries')
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->whereHas('deliveries', fn ($d) => $d->where('status', $status)))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($filters['per_page'] ?? 50))
            ->withQueryString();

        return EventResource::collection($events);
    }

    public function show(Event $event): EventResource
    {
        return new EventResource($event->load('deliveries.attemptLog'));
    }
}
