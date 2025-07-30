<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUtf8Encoding
{
    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Set UTF-8 encoding for the application
        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');

        $response = $next($request);

        // Ensure response has UTF-8 encoding
        if (method_exists($response, 'header')) {
            $response->header('Content-Type', 'text/html; charset=UTF-8');
        }

        return $response;
    }
}
