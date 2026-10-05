<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Admin\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = validator($request->query(), [
            'q' => ['sometimes', 'string', 'max:200'],
            'actor' => ['sometimes', 'string', 'max:254'],
            'action' => ['sometimes', 'string', 'max:64'],
            'target' => ['sometimes', 'string', 'max:200'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'in:10,20,50,100'],
        ])->validate();
        $query = AuditLog::query()->latest('created_at');

        $query->when(isset($validated['actor']), function (Builder $query) use ($validated): void {
            $actor = trim($validated['actor']);
            $query->where(function (Builder $query) use ($actor): void {
                $query->where('actor_user_id', $actor)
                    ->orWhereHas('actor', fn (Builder $actorQuery) => $actorQuery->where('normalized_email', mb_strtolower($actor)));
            });
        });
        $query->when(isset($validated['action']), fn (Builder $query) => $query->where('action_code', $validated['action']));
        $query->when(isset($validated['target']), function (Builder $query) use ($validated): void {
            $target = trim($validated['target']);
            $query->where(function (Builder $query) use ($target): void {
                $query->where('target_id', $target)->orWhere('target_type', 'like', '%'.$target.'%');
            });
        });
        $query->when(isset($validated['q']), function (Builder $query) use ($validated): void {
            $search = trim($validated['q']);
            $query->where(function (Builder $query) use ($search): void {
                $query->where('action_code', 'like', '%'.$search.'%')
                    ->orWhere('target_type', 'like', '%'.$search.'%')
                    ->orWhere('target_id', $search);
            });
        });
        $query->when(isset($validated['date_from']), fn (Builder $query) => $query->whereDate('created_at', '>=', $validated['date_from']));
        $query->when(isset($validated['date_to']), fn (Builder $query) => $query->whereDate('created_at', '<=', $validated['date_to']));
        $page = $query->paginate((int) ($validated['per_page'] ?? 20));

        return response()->json([
            'items' => AuditLogResource::collection($page->items())->resolve($request),
            'pagination' => [
                'page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'total_pages' => $page->lastPage(),
            ],
        ]);
    }
}
