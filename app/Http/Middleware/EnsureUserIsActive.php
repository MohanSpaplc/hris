<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Block a user who was deactivated after their token was issued, and revoke that token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            $user->currentAccessToken()?->delete();

            return response()->json(['message' => 'Your account is inactive. Contact HR.'], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
