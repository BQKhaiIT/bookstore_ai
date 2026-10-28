<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isStaff()) {
            return response()->json([
                'message' => 'Bạn không có quyền truy cập tài nguyên này.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403);
        }

        if (! $user->hasAdminPermission($permission)) {
            return response()->json([
                'message' => 'Bạn không có quyền thao tác trên module này.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
