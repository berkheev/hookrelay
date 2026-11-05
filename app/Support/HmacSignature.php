<?php

declare(strict_types=1);

namespace App\Support;

final class HmacSignature
{
    private const PREFIX = 'sha256=';

    public static function sign(string $payload, string $secret): string
    {
        return self::PREFIX . hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, string $secret, ?string $header): bool
    {
        if ($header === null || $header === '') {
            return false;
        }

        $given = strtolower(trim($header));
        if (str_starts_with($given, self::PREFIX)) {
            $given = substr($given, strlen(self::PREFIX));
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $given);
    }
}
