<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\HmacSignature;
use PHPUnit\Framework\TestCase;

final class HmacSignatureTest extends TestCase
{
    public function test_matches_github_reference_implementation(): void
    {
        // Example from GitHub's "Validating webhook deliveries" documentation.
        $this->assertSame(
            'sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17',
            HmacSignature::sign('Hello, World!', "It's a Secret to Everybody"),
        );
    }

    public function test_verify_accepts_prefixed_bare_and_uppercase_digests(): void
    {
        $signature = HmacSignature::sign('payload', 'secret');
        $digest = substr($signature, 7);

        $this->assertTrue(HmacSignature::verify('payload', 'secret', $signature));
        $this->assertTrue(HmacSignature::verify('payload', 'secret', $digest));
        $this->assertTrue(HmacSignature::verify('payload', 'secret', strtoupper($digest)));
    }

    public function test_verify_rejects_wrong_secret_payload_or_missing_header(): void
    {
        $signature = HmacSignature::sign('payload', 'secret');

        $this->assertFalse(HmacSignature::verify('payload', 'other', $signature));
        $this->assertFalse(HmacSignature::verify('payload!', 'secret', $signature));
        $this->assertFalse(HmacSignature::verify('payload', 'secret', null));
        $this->assertFalse(HmacSignature::verify('payload', 'secret', ''));
    }
}
