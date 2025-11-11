<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id'); // Optional name for the payroll period e.g., "January 2025 Payroll"
            $table->enum('status', ['draft', 'processing', 'closed'])->default('draft')->after('end_date');
            $table->timestamp('closed_at')->nullable()->after('status');
            $table->text('notes')->nullable()->after('closed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['name', 'status', 'closed_at', 'notes']);
        });
    }
};
