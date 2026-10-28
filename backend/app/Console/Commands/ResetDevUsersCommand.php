<?php

namespace App\Console\Commands;

use App\Models\AdminAuditLog;
use App\Models\AdminInvitation;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ResetDevUsersCommand extends Command
{
    protected $signature = 'users:reset-dev {--confirm : Xác nhận xóa toàn bộ users trên DB development}';

    protected $description = 'Làm sạch users và dữ liệu auth liên quan trên database development (an toàn)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Không chạy trên production.');

            return self::FAILURE;
        }

        $database = (string) config('database.connections.pgsql.database');
        if (! in_array($database, ['bookstore_ai', 'bookstore'], true)) {
            $this->error("Database không được phép: {$database}");

            return self::FAILURE;
        }

        if (! $this->option('confirm')) {
            $this->warn("Database: {$database}");
            $this->line('Chạy lại với --confirm để thực hiện.');

            return self::FAILURE;
        }

        if (Order::query()->exists()) {
            $this->error('BLOCKED: Bảng orders đang tham chiếu users (restrictOnDelete). Không xóa users để bảo toàn dữ liệu nghiệp vụ.');

            return self::FAILURE;
        }

        $backupDir = storage_path('app/backups');
        File::ensureDirectoryExists($backupDir);
        $backupPath = $backupDir.'/users_'.now()->format('Ymd_His').'.json';

        $users = User::query()->get(['id', 'name', 'email', 'role', 'status', 'created_at']);
        File::put($backupPath, $users->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->info("Backup: {$backupPath}");

        DB::transaction(function (): void {
            DB::table('sessions')->delete();
            if (Schema::hasTable('password_reset_tokens')) {
                DB::table('password_reset_tokens')->delete();
            }
            Review::query()->delete();
            if (Schema::hasTable('admin_audit_logs')) {
                AdminAuditLog::query()->delete();
            }
            if (Schema::hasTable('admin_invitations')) {
                AdminInvitation::query()->delete();
            }
            User::query()->delete();
        });

        $this->info('Đã làm sạch bảng users và dữ liệu auth liên quan.');

        return self::SUCCESS;
    }
}
