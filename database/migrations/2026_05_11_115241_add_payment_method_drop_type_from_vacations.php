<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->enum('payment_method', ['immediate', 'with_payroll'])
                ->default('immediate')
                ->after('status');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->enum('type', ['paid', 'unpaid'])->default('paid')->after('status');
        });

        DB::table('vacations')->update(['type' => 'paid']);

        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
