<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email đã được xác minh trước đó.',
                'code' => 'EMAIL_ALREADY_VERIFIED',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Email xác minh đã được gửi lại.',
        ]);
    }
}
