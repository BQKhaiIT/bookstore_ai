<?php

namespace App\Console\Commands;

use App\Services\ReviewsSliderDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedReviewsSliderDemoCommand extends Command
{
    protected $signature = 'demo:seed-reviews-slider
                            {--force : Cho phép chạy khi APP_ENV không phải local}';

    protected $description = 'Tạo 10 đơn/review demo (delivered + paid + approved) cho slider Được quan tâm — chỉ local';

    public function handle(ReviewsSliderDemoSeeder $seeder): int
    {
        if (! $this->isAllowedEnvironment()) {
            $this->error('Chỉ chạy trong APP_ENV=local (hoặc dùng --force).');

            return self::FAILURE;
        }

        if (! $this->databaseLooksSafe()) {
            $this->error('Database không phải môi trường dev local (bookstore_ai). Dừng để an toàn.');

            return self::FAILURE;
        }

        try {
            $stats = $seeder->seed();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Books selected', (string) $stats['books_selected']],
                ['Orders created (new)', (string) $stats['orders_created']],
                ['Payments created (new)', (string) $stats['payments_created']],
                ['Reviews approved (new)', (string) $stats['reviews_approved']],
            ],
        );

        if ($stats['books_selected'] < ReviewsSliderDemoSeeder::TARGET_BOOK_COUNT) {
            $this->warn('Không đủ sách published / chưa review để đạt 10 cuốn.');

            return self::FAILURE;
        }

        $this->info('Demo seed hoàn tất. Cleanup: php artisan demo:cleanup-reviews-slider');

        return self::SUCCESS;
    }

    private function isAllowedEnvironment(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return app()->environment('local');
    }

    private function databaseLooksSafe(): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $database = (string) DB::connection()->getDatabaseName();

        return str_contains(strtolower($database), 'bookstore');
    }
}
