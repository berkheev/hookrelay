<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Delivery;
use App\Queue\DeliveryQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

final class SweepCommand extends Command
{
    protected $signature = 'hookrelay:sweep {--grace=60 : Seconds a delivery may be overdue before it is re-queued}';

    protected $description = 'Re-queue pending deliveries whose message was lost or never published';

    public function handle(DeliveryQueue $queue): int
    {
        $now = Carbon::now();
        $count = 0;

        Delivery::query()
            ->pending()
            ->where('next_attempt_at', '<', $now->copy()->subSeconds((int) $this->option('grace')))
            ->where(fn ($q) => $q->whereNull('locked_until')->orWhere('locked_until', '<', $now))
            ->select('id')
            ->chunkById(500, function ($deliveries) use ($queue, &$count): void {
                foreach ($deliveries as $delivery) {
                    $queue->publish($delivery->id);
                    $count++;
                }
            });

        if ($count > 0) {
            $this->components->warn(sprintf('Re-queued %d overdue deliveries.', $count));
        }

        return self::SUCCESS;
    }
}
