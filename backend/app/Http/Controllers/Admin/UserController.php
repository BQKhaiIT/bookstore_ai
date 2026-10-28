<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->where('role', UserRole::Customer);

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%');
            });
        }

        if ($role = $request->string('role')->trim()->toString()) {
            $query->where('role', $role);
        }

        if ($status = $request->string('status')->trim()->toString()) {
            $query->where('status', $status);
        }

        $query->orderByDesc('created_at');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        return UserResource::collection($query->paginate($perPage))->response();
    }

    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        if ($user->role !== UserRole::Customer) {
            throw new HttpResponseException(response()->json([
                'message' => 'Chỉ có thể quản lý trạng thái khách hàng tại đây.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403));
        }

        if ($user->id === $request->user()->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể thay đổi trạng thái tài khoản của chính bạn.',
                'code' => 'CANNOT_UPDATE_SELF',
                'errors' => (object) [],
            ], 409));
        }

        $user->status = $request->validated('status');
        $user->save();

        return response()->json([
            'data' => new UserResource($user->fresh()),
        ]);
    }
}
