<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportException;
use Throwable;

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

        try {
            $user->sendEmailVerificationNotification();
        } catch (TransportException $exception) {
            report($exception);

            return response()->json([
                'message' => 'Không gửi được email (lỗi SMTP). Kiểm tra cấu hình MAIL_* trong .env hoặc dùng MAIL_MAILER=log khi dev.',
                'code' => 'MAIL_TRANSPORT_FAILED',
                'errors' => (object) [],
            ], 503);
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }

        return response()->json([
            'message' => 'Email xác minh đã được gửi lại.',
        ]);
    }
}
