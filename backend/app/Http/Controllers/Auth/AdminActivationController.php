<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\ActivationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminActivationRequest;
use App\Services\AdminInvitationService;
use Illuminate\Http\JsonResponse;

class AdminActivationController extends Controller
{
    public function store(AdminActivationRequest $request, AdminInvitationService $invitations): JsonResponse
    {
        try {
            $invitations->acceptInvitation(
                $request->validated('email'),
                $request->validated('token'),
                $request->validated('password'),
                $request->validated('invitation'),
            );
        } catch (ActivationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
                'errors' => (object) [],
            ], 422);
        }

        return response()->json([
            'message' => 'Kích hoạt thành công. Bạn có thể đăng nhập tại trang quản trị.',
        ]);
    }
}
