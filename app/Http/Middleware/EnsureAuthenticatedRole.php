<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthenticatedRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $expectedClass = match ($role) {
            'admin' => \App\Models\Admin::class,
            'manager' => \App\Models\Manager::class,
            'client', 'user' => \App\Models\User::class,
            default => null,
        };

        if (!$expectedClass) {
            return response()->json(['message' => 'Invalid role constraint.'], 500);
        }

        if (!($user instanceof $expectedClass)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return $next($request);
    }
}
