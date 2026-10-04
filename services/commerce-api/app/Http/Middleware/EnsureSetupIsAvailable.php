<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Http\Responses\ProblemDetails;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupIsAvailable
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (User::query()->where('role', UserRole::Admin)->exists()) {
            return ProblemDetails::response(
                $request,
                409,
                'Setup unavailable',
                'Administrator setup is no longer available.',
                'setup_unavailable',
            );
        }

        return $next($request);
    }
}
