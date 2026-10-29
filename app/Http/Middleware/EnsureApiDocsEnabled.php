<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiDocsEnabled
{
    /**
     * Swagger docs are a map of the API, so hide them (404) unless explicitly enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('hris.api_docs_enabled'), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
