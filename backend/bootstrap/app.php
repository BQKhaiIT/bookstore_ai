<?php

use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->preventRequestForgery(except: [], allowSameSite: true);

        $middleware->web(replace: [
            PreventRequestForgery::class => VerifyCsrfToken::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'super_admin' => EnsureSuperAdmin::class,
            'permission' => EnsureAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Bạn cần đăng nhập để thực hiện thao tác này.',
                    'code' => 'UNAUTHENTICATED',
                    'errors' => (object) [],
                ], 401);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson() || $request->is('login', 'register', 'forgot-password', 'reset-password')) {
                $message = collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.';

                return response()->json([
                    'message' => $message,
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Bạn không có quyền thực hiện thao tác này.',
                    'code' => 'FORBIDDEN',
                    'errors' => (object) [],
                ], 403);
            }
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Quá nhiều yêu cầu. Vui lòng thử lại sau.',
                    'code' => 'TOO_MANY_REQUESTS',
                    'errors' => (object) [],
                ], 429);
            }
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() === 419 && $request->expectsJson()) {
                return response()->json([
                    'message' => 'Phiên làm việc hoặc CSRF token không hợp lệ.',
                    'code' => 'CSRF_TOKEN_MISMATCH',
                    'errors' => (object) [],
                ], 419);
            }
        });
    })->create();
