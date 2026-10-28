<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\ActivationException;
use App\Models\AdminInvitation;
use App\Models\User;
use App\Notifications\AdminActivationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminInvitationService
{
    public function __construct(private AdminAuditService $audit) {}

    /**
     * @param  list<string>  $permissions
     */
    public function createSuperAdminInvitation(string $name, string $email): AdminInvitation
    {
        $email = strtolower(trim($email));

        if (User::query()->where('role', UserRole::SuperAdmin)->exists()) {
            throw new \RuntimeException('Super Admin đã tồn tại.');
        }

        if (User::query()->where('email', $email)->exists()) {
            throw new \RuntimeException('Email đã được sử dụng.');
        }

        return DB::transaction(function () use ($name, $email) {
            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make(Str::password(32)),
                'role' => UserRole::SuperAdmin,
                'status' => UserStatus::Active,
                'email_verified_at' => null,
                'admin_permissions' => null,
            ]);

            [$invitation, $plainToken] = $this->storeInvitation(
                email: $email,
                name: $name,
                role: UserRole::SuperAdmin,
                permissions: null,
                invitedBy: null,
            );

            $user->notifyNow(new AdminActivationNotification($invitation->id, $plainToken));

            return $invitation;
        });
    }

    public function resendSuperAdminActivation(string $email): void
    {
        $email = strtolower(trim($email));

        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! $user->isSuperAdmin()) {
            throw new \RuntimeException('Không tìm thấy Super Admin với email đã cho.');
        }

        if ($user->hasVerifiedEmail()) {
            throw new \RuntimeException('Tài khoản đã kích hoạt, không gửi lại email.');
        }

        $accepted = AdminInvitation::query()
            ->where('email', $email)
            ->whereNotNull('accepted_at')
            ->exists();

        if ($accepted) {
            throw new \RuntimeException('Lời mời đã được sử dụng.');
        }

        AdminInvitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

        [$invitation, $plainToken] = $this->storeInvitation(
            email: $email,
            name: $user->name,
            role: UserRole::SuperAdmin,
            permissions: null,
            invitedBy: null,
        );

        $user->notifyNow(new AdminActivationNotification($invitation->id, $plainToken));
    }

    /**
     * @param  list<string>  $permissions
     */
    public function inviteAdmin(User $inviter, string $name, string $email, array $permissions): AdminInvitation
    {
        if (! $inviter->isSuperAdmin()) {
            throw new \RuntimeException('Chỉ Super Admin được mời Admin.');
        }

        $email = strtolower(trim($email));

        if (User::query()->where('email', $email)->exists()) {
            throw new \RuntimeException('Email đã được sử dụng.');
        }

        if (AdminInvitation::query()->where('email', $email)->whereNull('accepted_at')->where('expires_at', '>', now())->exists()) {
            throw new \RuntimeException('Đã có lời mời đang chờ xử lý cho email này.');
        }

        [$invitation, $plainToken] = $this->storeInvitation(
            email: $email,
            name: $name,
            role: UserRole::Admin,
            permissions: $permissions,
            invitedBy: $inviter->id,
        );

        $this->audit->log($inviter, 'admin.invited', AdminInvitation::class, $invitation->id, [
            'email' => $email,
            'permissions' => $permissions,
        ]);

        \Illuminate\Support\Facades\Notification::route('mail', $email)
            ->notify(new AdminActivationNotification($invitation->id, $plainToken));

        return $invitation;
    }

    public function acceptInvitation(string $email, string $token, string $password, ?int $invitationId = null): User
    {
        $email = strtolower(trim($email));
        $token = trim($token);

        return DB::transaction(function () use ($email, $token, $password, $invitationId) {
            $invitation = $this->lockPendingInvitation($email, $token, $invitationId);
            $email = strtolower($invitation->email);

            $user = User::query()->where('email', $email)->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $invitation->name,
                    'email' => $email,
                    'password' => $password,
                    'role' => $invitation->role,
                    'status' => UserStatus::Active,
                    'admin_permissions' => $invitation->permissions,
                    'email_verified_at' => now(),
                ]);
            } else {
                if (! $user->role->isStaff()) {
                    throw new \RuntimeException('Email đã thuộc tài khoản khách hàng.');
                }

                $user->password = $password;
                $user->email_verified_at = now();
                if ($user->role === UserRole::Admin) {
                    $user->admin_permissions = $invitation->permissions;
                }
                $user->save();
            }

            $invitation->accepted_at = now();
            $invitation->save();

            $this->invalidateDatabaseSessionsForUser($user->id);

            return $user->fresh();
        });
    }

    private function invalidateDatabaseSessionsForUser(int $userId): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)->where('user_id', $userId)->delete();
    }

    private function lockPendingInvitation(string $email, string $token, ?int $invitationId): AdminInvitation
    {
        $hash = hash('sha256', $token);

        /** @var AdminInvitation|null $invitation */
        $invitation = null;

        if ($invitationId !== null) {
            $byId = AdminInvitation::query()->lockForUpdate()->find($invitationId);

            if ($byId === null && $this->hasPendingInvitation($email)) {
                throw new ActivationException(
                    'ACTIVATION_SUPERSEDED',
                    'Liên kết không còn hiệu lực. Hãy dùng email kích hoạt mới nhất.',
                );
            }

            if ($byId !== null && hash_equals($byId->token_hash, $hash)) {
                $invitation = $byId;
            } elseif ($byId !== null) {
                throw new ActivationException(
                    'ACTIVATION_INVALID',
                    'Liên kết kích hoạt không hợp lệ. Kiểm tra email mới nhất hoặc yêu cầu gửi lại.',
                );
            }
        }

        if ($invitation === null) {
            $invitation = AdminInvitation::query()
                ->lockForUpdate()
                ->where('email', $email)
                ->where('token_hash', $hash)
                ->first();
        }

        if ($invitation !== null) {
            if ($invitation->isAccepted()) {
                throw new ActivationException(
                    'ACTIVATION_USED',
                    'Liên kết kích hoạt đã được sử dụng. Hãy đăng nhập hoặc đặt lại mật khẩu.',
                );
            }

            if ($invitation->isExpired()) {
                throw new ActivationException(
                    'ACTIVATION_EXPIRED',
                    'Liên kết kích hoạt đã hết hạn. Yêu cầu gửi lại email kích hoạt.',
                );
            }

            return $invitation;
        }

        if ($invitationId !== null && $this->hasPendingInvitation($email)) {
            throw new ActivationException(
                'ACTIVATION_SUPERSEDED',
                'Liên kết không còn hiệu lực. Hãy dùng email kích hoạt mới nhất.',
            );
        }

        if (AdminInvitation::query()->where('email', $email)->whereNotNull('accepted_at')->exists()) {
            throw new ActivationException(
                'ACTIVATION_USED',
                'Liên kết kích hoạt đã được sử dụng. Hãy đăng nhập hoặc đặt lại mật khẩu.',
            );
        }

        if (AdminInvitation::query()->where('email', $email)->where('expires_at', '<=', now())->exists()) {
            throw new ActivationException(
                'ACTIVATION_EXPIRED',
                'Liên kết kích hoạt đã hết hạn. Yêu cầu gửi lại email kích hoạt.',
            );
        }

        throw new ActivationException(
            'ACTIVATION_INVALID',
            'Liên kết kích hoạt không hợp lệ. Kiểm tra email mới nhất hoặc yêu cầu gửi lại.',
        );
    }

    private function hasPendingInvitation(string $email): bool
    {
        return AdminInvitation::query()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * @param  list<string>|null  $permissions
     * @return array{0: AdminInvitation, 1: string}
     */
    private function storeInvitation(string $email, string $name, UserRole $role, ?array $permissions, ?int $invitedBy): array
    {
        $email = strtolower(trim($email));
        $plainToken = Str::random(64);

        $invitation = AdminInvitation::query()->create([
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'permissions' => $permissions,
            'token_hash' => hash('sha256', $plainToken),
            'invited_by' => $invitedBy,
            'expires_at' => now()->addHours(48),
        ]);

        return [$invitation, $plainToken];
    }
}
