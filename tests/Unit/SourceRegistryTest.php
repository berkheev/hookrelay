<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Sources\Source;
use App\Sources\SourceRegistry;
use PHPUnit\Framework\TestCase;

final class SourceRegistryTest extends TestCase
{
    public function test_loads_the_example_configuration(): void
    {
        $registry = SourceRegistry::fromFile(__DIR__ . '/../../sources.example.json');

        $github = $registry->get('github');
        $this->assertNotNull($github);
        $this->assertSame('X-Hub-Signature-256', $github->signatureHeader);
        $this->assertSame('X-GitHub-Delivery', $github->idempotencyHeader);
        $this->assertTrue($github->requiresSignature());
        $this->assertCount(2, $github->destinations);

        $this->assertFalse($registry->get('legacy-crm')?->requiresSignature());
        $this->assertNull($registry->get('missing'));
    }

    public function test_missing_file_means_no_sources(): void
    {
        $this->assertSame([], SourceRegistry::fromFile('/does/not/exist.json')->all());
    }

    public function test_rejects_source_without_destinations(): void
    {
        $this->expectExceptionMessage('needs at least one destination');

        Source::fromArray('github', ['destinations' => []]);
    }

    public function test_rejects_non_http_destinations(): void
    {
        $this->expectExceptionMessage('must be an http(s) URL');

        Source::fromArray('github', ['destinations' => ['file:///etc/passwd']]);
    }

    public function test_rejects_unsafe_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Source::fromArray('../etc', ['destinations' => ['https://example.com']]);
    }
}
