<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Short-circuit ALL OPTIONS preflight requests before any auth
 * middleware can reject them. HandleCors (registered before this)
 * already appends the correct Access-Control-* headers; we just
 * need to return 204 immediately so the browser's preflight succeeds.
 */
class HandleOptionsRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('OPTIONS')) {
            return response('', 204);
        }

        return $next($request);
    }
}
