<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Usage: ->middleware('role:hr,tag_manager')
     */
    public function handle(Request $request, Closure $next, string ...$roleCodes): Response
    {
        abort_unless($request->user()?->hasRole(...$roleCodes), Response::HTTP_FORBIDDEN, 'This action is unauthorized.');

        return $next($request);
    }
}
