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
        Schema::table('absences', function (Blueprint $table) {
            $table->foreignId('employee_leave_id')
                ->nullable()
                ->after('employee_deduction_id')
                ->constrained('employee_leaves')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absences', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\EmployeeLeave::class);
            $table->dropColumn('employee_leave_id');
        });
    }
};
