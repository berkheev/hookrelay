<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Delivery\ReplayService;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;

final class DeliveryController
{
    public function replay(Delivery $delivery, ReplayService $replay): JsonResponse
    {
        return (new DeliveryResource($replay->replay($delivery)))
            ->response()
            ->setStatusCode(202);
    }
}
