<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdatePasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request): JsonResponse
    {
        $request->validateCurrentPassword();

        $user = $request->user();

        $user->password = $request->validated('password');
        $user->save();

        $sessions = DB::table('sessions')->where('user_id', $user->id);
        if ($request->hasSession()) {
            $sessions->where('id', '!=', $request->session()->getId());
        }
        $sessions->delete();

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Mật khẩu đã được cập nhật.',
        ]);
    }
}
