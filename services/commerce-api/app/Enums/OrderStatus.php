<?php

namespace App\Enums;

enum OrderStatus: string
{
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case PaymentFailed = 'payment_failed';
    case Expired = 'expired';
    case ManualReview = 'manual_review';
    case ReviewProcessed = 'review_processed';
}
