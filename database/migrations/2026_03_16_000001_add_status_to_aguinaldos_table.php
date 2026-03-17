<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aguinaldos', function (Blueprint $table) {
            $table->enum('status', ['pending', 'paid'])->default('pending')->after('generated_at');
            $table->timestamp('paid_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('aguinaldos', function (Blueprint $table) {
            $table->dropColumn(['status', 'paid_at']);
        });
    }
};
