<?php

namespace App\Services;

use App\Enums\ManualReviewStatus;
use App\Enums\OrderStatus;
use App\Exceptions\ManualReviewConflictException;
use App\Http\Requests\Api\V1\Admin\ResolveManualReviewRequest;
use App\Models\ManualReview;
use App\Models\Order;
use App\Models\OrderStateHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ManualReviewService
{
    public function __construct(private readonly AdminAuditLogger $audit) {}

    public function resolve(
        ResolveManualReviewRequest $request,
        ManualReview $review,
        string $note,
        int $expectedVersion,
    ): Order {
        return DB::transaction(function () use ($request, $review, $note, $expectedVersion): Order {
            $lockedReview = ManualReview::query()->lockForUpdate()->findOrFail($review->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedReview->order_id);

            if ($lockedReview->status !== ManualReviewStatus::Pending || $order->status !== OrderStatus::ManualReview) {
                throw new ManualReviewConflictException('The manual review has already been resolved.');
            }

            if ($order->version !== $expectedVersion) {
                throw new ManualReviewConflictException('The order changed after it was loaded.');
            }

            $actor = $request->user();

            if (! $actor instanceof User) {
                throw new ManualReviewConflictException('The resolving administrator is unavailable.');
            }

            $databaseNow = CarbonImmutable::parse((string) DB::scalar('SELECT CURRENT_TIMESTAMP'))->utc();
            $before = $lockedReview->toArray();
            $lockedReview->forceFill([
                'status' => ManualReviewStatus::Processed,
                'resolution_note' => $note,
                'resolved_by_user_id' => $actor->id,
                'resolved_at' => $databaseNow,
            ])->save();
            $from = $order->status;
            $order->forceFill([
                'status' => OrderStatus::ReviewProcessed,
                'version' => $order->version + 1,
            ])->save();
            $history = new OrderStateHistory;
            $history->forceFill([
                'order_id' => $order->id,
                'from_status' => $from,
                'to_status' => OrderStatus::ReviewProcessed,
                'reason_code' => 'manual_review_processed',
                'metadata' => [],
                'actor_user_id' => $actor->id,
            ])->save();
            $this->audit->record(
                $request,
                'manual_review.processed',
                $lockedReview,
                $before,
                $lockedReview->fresh()->toArray(),
            );

            return $order->fresh();
        }, 3);
    }
}
