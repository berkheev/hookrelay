<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SinkController
{
    public function __invoke(Request $request, string $mode): JsonResponse
    {
        $status = match ($mode) {
            'ok' => 200,
            'flaky' => random_int(1, 10) > 3 ? 200 : 503,
            'down' => 503,
            'reject' => 422,
            default => 404,
        };

        return new JsonResponse([
            'received' => $status < 300,
            'event_id' => $request->header('x-hookrelay-event-id'),
            'attempt' => $request->header('x-hookrelay-attempt'),
        ], $status);
    }
}
