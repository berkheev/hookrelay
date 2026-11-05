<?php

declare(strict_types=1);

namespace App\Ingest;

use App\Models\Event;

final readonly class IngestResult
{
    private function __construct(
        public Event $event,
        public bool $duplicate,
        public int $deliveries,
    ) {
    }

    public static function accepted(Event $event, int $deliveries): self
    {
        return new self($event, false, $deliveries);
    }

    public static function duplicate(Event $event): self
    {
        return new self($event, true, 0);
    }
}
