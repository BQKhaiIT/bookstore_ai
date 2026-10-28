<?php

namespace App\Console\Commands;

use App\Services\AdminInvitationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'admin:create-super
                            {--name= : Họ tên Super Admin}
                            {--email= : Email Super Admin}
                            {--force : Bỏ qua xác nhận cuối}
                            {--resend-activation : Gửi lại email kích hoạt cho Super Admin chưa kích hoạt}';

    protected $description = 'Tạo Super Admin đầu tiên và gửi email kích hoạt mật khẩu';

    public function handle(AdminInvitationService $invitations): int
    {
        $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email Super Admin'))));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email không hợp lệ.');

            return self::FAILURE;
        }

        if ($this->option('resend-activation')) {
            try {
                $invitations->resendSuperAdminActivation($email);
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $this->info('Đã gửi lại email kích hoạt.');

            return self::SUCCESS;
        }

        $name = $this->option('name') ?: $this->ask('Họ tên Super Admin');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email']],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm("Tạo Super Admin cho {$email}?", true)) {
            $this->warn('Đã hủy.');

            return self::FAILURE;
        }

        try {
            $invitations->createSuperAdminInvitation($name, $email);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Super Admin đã được tạo. Email kích hoạt đã được gửi.');
        $this->line('Đăng nhập sau khi kích hoạt: '.rtrim((string) config('app.frontend_url'), '/').'/admin/login');

        return self::SUCCESS;
    }
}
