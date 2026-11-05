<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int            $id
 * @property string         $event_id
 * @property string         $destination
 * @property DeliveryStatus $status
 * @property int            $attempts
 * @property Carbon|null    $next_attempt_at
 * @property Carbon|null    $locked_until
 * @property int|null       $last_status_code
 * @property string|null    $last_error
 * @property Carbon|null    $delivered_at
 * @property Carbon         $created_at
 * @property Carbon         $updated_at
 * @property-read Event     $event
 */
class Delivery extends Model
{
    protected $guarded = [];

    protected $attributes = [
        'status' => 'pending',
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'last_status_code' => 'integer',
            'next_attempt_at' => 'immutable_datetime',
            'locked_until' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<DeliveryAttempt, $this>
     */
    public function attemptLog(): HasMany
    {
        return $this->hasMany(DeliveryAttempt::class)->orderBy('number');
    }

    /**
     * @param Builder<Delivery> $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Pending);
    }

    public function isRetrying(): bool
    {
        return $this->status === DeliveryStatus::Pending && $this->attempts > 0;
    }

    public function destinationHost(): string
    {
        $host = parse_url($this->destination, PHP_URL_HOST);
        $port = parse_url($this->destination, PHP_URL_PORT);

        return (is_string($host) ? $host : $this->destination) . ($port ? ':' . $port : '');
    }
}
