<?php

namespace App\Services;

use App\Http\Responses\ProblemDetails;
use App\Models\DomainModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OptimisticConcurrency
{
    public function expectedVersion(Request $request): int|JsonResponse
    {
        $validator = Validator::make(
            ['version' => $request->header('If-Match-Version')],
            ['version' => ['required', 'integer', 'min:1']],
        );

        if ($validator->fails()) {
            return ProblemDetails::validation($request, ['If-Match-Version' => $validator->errors()->all()]);
        }

        return (int) $validator->validated()['version'];
    }

    public function conflictIfStale(Request $request, DomainModel $model, int $expectedVersion): ?JsonResponse
    {
        if ((int) $model->getAttribute('version') === $expectedVersion) {
            return null;
        }

        return ProblemDetails::response(
            $request,
            409,
            'Version conflict',
            'The resource was modified after the supplied version was read.',
            'stale_version',
        );
    }
}
