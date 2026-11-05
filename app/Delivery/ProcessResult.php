<?php

declare(strict_types=1);

namespace App\Delivery;

enum ProcessResult: string
{
    case Delivered = 'delivered';
    case Retrying = 'retrying';
    case Failed = 'failed';
    case Postponed = 'postponed';
    case Skipped = 'skipped';
}
