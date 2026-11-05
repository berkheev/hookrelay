<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string                $id
 * @property string                $source
 * @property string|null           $idempotency_key
 * @property string|null           $type
 * @property array<string, string> $headers
 * @property string                $body
 * @property Carbon                $received_at
 */
class Event extends Model
{
    use HasUuids;

    private const TYPE_HEADERS = ['x-github-event', 'x-gitlab-event', 'x-shopify-topic', 'x-event-type'];

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'received_at' => 'immutable_datetime',
        ];
    }

    public function newUniqueId(): string
    {
        return (string) Str::uuid7();
    }

    /**
     * @return HasMany<Delivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class)->orderBy('id');
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * @param array<string, string> $headers
     */
    public static function detectType(array $headers, string $body): ?string
    {
        foreach (self::TYPE_HEADERS as $header) {
            if (isset($headers[$header]) && $headers[$header] !== '') {
                return mb_substr($headers[$header], 0, 255);
            }
        }

        $payload = json_decode($body, true);
        if (is_array($payload)) {
            foreach (['type', 'event', 'event_type'] as $key) {
                if (isset($payload[$key]) && is_string($payload[$key])) {
                    return mb_substr($payload[$key], 0, 255);
                }
            }
        }

        return null;
    }

    public function prettyBody(): string
    {
        $decoded = json_decode($this->body);

        return json_last_error() !== JSON_ERROR_NONE
            ? $this->body
            : (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
