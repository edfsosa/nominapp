<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->decimal('payment_amount', 14, 2)->nullable()->after('business_days');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid')->after('payment_amount');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn(['payment_amount', 'payment_status', 'paid_at']);
        });
    }
};
