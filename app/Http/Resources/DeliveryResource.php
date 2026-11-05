<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Delivery
 */
final class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'destination' => $this->destination,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'next_attempt_at' => $this->next_attempt_at?->toAtomString(),
            'last_status_code' => $this->last_status_code,
            'last_error' => $this->last_error,
            'delivered_at' => $this->delivered_at?->toAtomString(),
            'attempt_log' => $this->whenLoaded('attemptLog', fn () => $this->attemptLog->map(fn (DeliveryAttempt $a) => [
                'number' => $a->number,
                'status_code' => $a->status_code,
                'duration_ms' => $a->duration_ms,
                'error' => $a->error,
                'response_excerpt' => $a->response_excerpt,
                'at' => $a->created_at->toAtomString(),
            ])),
        ];
    }
}
