<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
final class EventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $detailed = $request->routeIs('api.events.show');

        return [
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
            'idempotency_key' => $this->idempotency_key,
            'received_at' => $this->received_at->toAtomString(),
            'headers' => $this->when($detailed, $this->headers),
            'body' => $this->when($detailed, $this->body),
            'deliveries' => DeliveryResource::collection($this->whenLoaded('deliveries')),
        ];
    }
}
