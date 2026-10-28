<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteAdminRequest;
use App\Http\Requests\Admin\UpdateStaffPermissionsRequest;
use App\Http\Requests\Admin\UpdateStaffStatusRequest;
use App\Http\Resources\AdminInvitationResource;
use App\Http\Resources\UserResource;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Services\AdminAuditService;
use App\Services\AdminInvitationService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staff = User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::SuperAdmin->value])
            ->orderByDesc('created_at')
            ->get();

        $pending = AdminInvitation::query()
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => [
                'staff' => UserResource::collection($staff),
                'pending_invitations' => AdminInvitationResource::collection($pending),
                'available_permissions' => AdminPermission::values(),
            ],
        ]);
    }

    public function invite(InviteAdminRequest $request, AdminInvitationService $invitations): JsonResponse
    {
        try {
            $invitation = $invitations->inviteAdmin(
                $request->user(),
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('permissions'),
            );
        } catch (\RuntimeException $e) {
            throw new HttpResponseException(response()->json([
                'message' => $e->getMessage(),
                'code' => 'INVITE_FAILED',
                'errors' => (object) [],
            ], 422));
        }

        return response()->json([
            'data' => new AdminInvitationResource($invitation),
            'message' => 'Đã gửi lời mời kích hoạt qua email.',
        ], 201);
    }

    public function updatePermissions(
        UpdateStaffPermissionsRequest $request,
        User $user,
        AdminAuditService $audit,
    ): JsonResponse {
        $this->assertManageableStaff($request->user(), $user);

        $user->syncAdminPermissions($request->validated('permissions'));
        $user->save();

        DB::table('sessions')->where('user_id', $user->id)->delete();

        $audit->log($request->user(), 'staff.permissions_updated', User::class, $user->id, [
            'permissions' => $user->admin_permissions,
        ], $request);

        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    public function updateStatus(
        UpdateStaffStatusRequest $request,
        User $user,
        AdminAuditService $audit,
    ): JsonResponse {
        $this->assertManageableStaff($request->user(), $user);

        if ($user->isSuperAdmin() && $request->validated('status') === UserStatus::Blocked->value) {
            $activeSuperAdmins = User::query()
                ->where('role', UserRole::SuperAdmin)
                ->where('status', UserStatus::Active)
                ->where('id', '!=', $user->id)
                ->count();

            if ($activeSuperAdmins === 0) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Không thể khóa Super Admin cuối cùng.',
                    'code' => 'LAST_SUPER_ADMIN',
                    'errors' => (object) [],
                ], 409));
            }
        }

        $user->status = UserStatus::from($request->validated('status'));
        $user->save();

        if ($user->status === UserStatus::Blocked) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        $audit->log($request->user(), 'staff.status_updated', User::class, $user->id, [
            'status' => $user->status->value,
        ], $request);

        return response()->json(['data' => new UserResource($user->fresh())]);
    }

    public function destroyInvitation(Request $request, AdminInvitation $invitation, AdminAuditService $audit): JsonResponse
    {
        if ($invitation->isAccepted()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Lời mời đã được chấp nhận.',
                'code' => 'INVITE_ALREADY_ACCEPTED',
                'errors' => (object) [],
            ], 409));
        }

        $audit->log($request->user(), 'admin.invitation_cancelled', AdminInvitation::class, $invitation->id, [
            'email' => $invitation->email,
        ], $request);

        $invitation->delete();

        return response()->json(['message' => 'Đã hủy lời mời.']);
    }

    private function assertManageableStaff(User $actor, User $target): void
    {
        if (! $actor->isSuperAdmin()) {
            throw new HttpResponseException(response()->json([
                'message' => 'Chỉ Super Admin được quản lý nhân sự quản trị.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403));
        }

        if ($target->role === UserRole::Customer) {
            throw new HttpResponseException(response()->json([
                'message' => 'Tài khoản không thuộc phạm vi quản lý nhân sự.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403));
        }

        if ($target->isSuperAdmin() && $target->id !== $actor->id) {
            throw new HttpResponseException(response()->json([
                'message' => 'Không thể quản lý tài khoản Super Admin khác.',
                'code' => 'FORBIDDEN',
                'errors' => (object) [],
            ], 403));
        }
    }
}
