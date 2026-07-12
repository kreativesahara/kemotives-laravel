<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user || $user->roles === null) {
            return response()->json(['error' => 'Unauthorized: Missing roles'], 401);
        }

        $allowedRoles = array_map('intval', $roles);
        
        if (!in_array($user->roles, $allowedRoles, true)) {
            return response()->json(['error' => 'Unauthorized: Role mismatch'], 401);
        }

        return $next($request);
    }
}
