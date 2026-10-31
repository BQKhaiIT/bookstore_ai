<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('method');
            $table->string('environment', 20)->nullable()->after('provider');
            $table->string('invoice_number', 64)->nullable()->after('environment');
            $table->json('metadata')->nullable()->after('transaction_reference');

            $table->unique('invoice_number');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_payments_one_paid_per_order ON payments (order_id) WHERE status = \'paid\''
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS uq_payments_one_paid_per_order');
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->dropColumn(['provider', 'environment', 'invoice_number', 'metadata']);
        });
    }
};
