<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Customer,
            'status' => UserStatus::Active,
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return response()->json([
            'data' => new UserResource($user->fresh()),
            'message' => $user->hasVerifiedEmail()
                ? null
                : 'Đăng ký thành công. Nếu chưa nhận email xác minh, hãy mở trang xác minh và bấm gửi lại.',
        ], 201);
    }
}
