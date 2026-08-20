<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom CORS Middleware
 *
 * Handles CORS at the PHP level to bypass LiteSpeed/cPanel limitations
 * with mod_headers. This ensures CORS headers are always present
 * regardless of web server configuration.
 */
class CorsMiddleware
{
    /**
     * Allowed origins for CORS requests.
     */
    private array $allowedOrigins = [
        'https://kemotives.co.ke',
        'https://www.kemotives.co.ke',
        'http://localhost:3000',
        'http://localhost:4000',
        'http://localhost:5173',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // For preflight OPTIONS requests, return immediately with CORS headers
        if ($request->isMethod('OPTIONS')) {
            $response = response('', 204);
            return $this->addCorsHeaders($request, $response);
        }

        // For all other requests, process normally then add CORS headers
        $response = $next($request);
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Add CORS headers to the response if the origin is allowed.
     */
    private function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->headers->get('Origin');

        if (!$origin) {
            return $response;
        }

        // Check if origin is in our allowed list
        if (!in_array($origin, $this->allowedOrigins, true)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept, Origin, X-Requested-With, X-CSRF-TOKEN, X-XSRF-TOKEN');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        $response->headers->set('Access-Control-Expose-Headers', 'Authorization');
        $response->headers->set('Vary', 'Origin');

        return $response;
    }
}
