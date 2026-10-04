<?php

namespace App\Http\Middleware;

use App\Http\Responses\ProblemDetails;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ProblemDetails::response(
                $request,
                401,
                'Unauthenticated',
                'Authentication is required.',
                'unauthenticated',
            );
        }

        if (! in_array($user->roleValue(), $roles, true)) {
            return ProblemDetails::response(
                $request,
                403,
                'Forbidden',
                'The authenticated principal does not have access to this capability.',
                'forbidden',
            );
        }

        return $next($request);
    }
}
