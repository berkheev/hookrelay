<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Sources\SourceRegistry;
use App\Support\HmacSignature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class DemoCommand extends Command
{
    protected $signature = 'hookrelay:demo {--url=http://app:8000 : Base URL of the relay} {--count=12}';

    protected $description = 'Send sample webhooks to a running hookrelay instance';

    public function handle(SourceRegistry $sources): int
    {
        $base = rtrim((string) $this->option('url'), '/');

        $samples = [
            ['github', 'push', ['ref' => 'refs/heads/main', 'repository' => ['full_name' => 'acme/shop'], 'commits' => [['id' => Str::random(40), 'message' => 'Fix checkout rounding']]]],
            ['github', 'pull_request', ['action' => 'opened', 'number' => random_int(100, 999), 'pull_request' => ['title' => 'Add Apple Pay']]],
            ['github', 'workflow_run', ['action' => 'completed', 'workflow_run' => ['conclusion' => 'success', 'name' => 'CI']]],
            ['billing', null, ['type' => 'invoice.paid', 'data' => ['invoice' => 'in_' . Str::random(12), 'amount' => 4900, 'currency' => 'eur']]],
            ['billing', null, ['type' => 'customer.subscription.updated', 'data' => ['plan' => 'pro', 'seats' => 12]]],
            ['billing', null, ['type' => 'charge.refunded', 'data' => ['charge' => 'ch_' . Str::random(12), 'amount' => 1900]]],
            ['legacy-crm', null, ['event' => 'contact.created', 'email' => 'jane@example.com']],
        ];

        for ($i = 0; $i < (int) $this->option('count'); $i++) {
            [$sourceName, $githubEvent, $payload] = $samples[$i % count($samples)];
            $source = $sources->get($sourceName);
            if ($source === null) {
                $this->components->warn("Source {$sourceName} is not configured, skipping.");
                continue;
            }

            $body = (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
            $headers = ['Content-Type' => 'application/json'];

            if ($githubEvent !== null) {
                $headers['X-GitHub-Event'] = $githubEvent;
            }
            if ($source->idempotencyHeader !== null) {
                $headers[$source->idempotencyHeader] = (string) Str::uuid();
            }
            if ($source->secret !== null) {
                $headers[$source->signatureHeader] = HmacSignature::sign($body, $source->secret);
            }

            $response = Http::withHeaders($headers)->withBody($body, 'application/json')->post("{$base}/hooks/{$sourceName}");

            $this->line(sprintf('%-11s %-32s → %d %s', $sourceName, $githubEvent ?? $payload['type'] ?? $payload['event'], $response->status(), $response->json('id')));
        }

        return self::SUCCESS;
    }
}
