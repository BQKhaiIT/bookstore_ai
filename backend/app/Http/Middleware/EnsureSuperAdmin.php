<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isSuperAdmin()) {
            return response()->json([
                'message' => 'Chỉ Super Admin được thực hiện thao tác này.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
