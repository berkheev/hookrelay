<?php

declare(strict_types=1);

namespace App\Delivery;

use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class DeliveryInFlightException extends \RuntimeException
{
    public function __construct(public readonly Delivery $delivery)
    {
        parent::__construct(sprintf('Delivery #%d is being sent right now, try again in a few seconds.', $delivery->id));
    }

    public function report(): bool
    {
        return true;
    }

    public function render(Request $request): Response
    {
        if ($request->expectsJson()) {
            return new JsonResponse(['error' => 'delivery_in_flight', 'message' => $this->getMessage()], 409);
        }

        return back()->with('status', $this->getMessage());
    }
}
