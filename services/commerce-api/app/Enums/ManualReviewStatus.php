<?php

namespace App\Enums;

enum ManualReviewStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
}
