<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->boolean('generates_deduction')->default(false)->after('end_time');
            $table->foreignId('employee_deduction_id')->nullable()->after('generates_deduction')
                ->constrained('employee_deductions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employee_leaves', function (Blueprint $table) {
            $table->dropForeign(['employee_deduction_id']);
            $table->dropColumn(['start_time', 'end_time', 'generates_deduction', 'employee_deduction_id']);
        });
    }
};
