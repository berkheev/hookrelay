<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class PruneCommand extends Command
{
    protected $signature = 'hookrelay:prune {--days= : Keep events this many days (default: HOOKRELAY_RETENTION_DAYS)}';

    protected $description = 'Delete old events together with their deliveries and attempt logs';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('hookrelay.retention_days'));
        $before = Carbon::now()->subDays($days);
        $total = 0;

        do {
            $ids = Event::query()->where('received_at', '<', $before)->limit(1000)->pluck('id');
            $total += Event::query()->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        $this->components->info(sprintf('Pruned %d events older than %d days.', $total, $days));

        return self::SUCCESS;
    }
}
