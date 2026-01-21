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
        Schema::create('employee_vacation_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->unsignedTinyInteger('years_of_service')->default(0);
            $table->unsignedTinyInteger('entitled_days')->default(0);
            $table->unsignedTinyInteger('used_days')->default(0);
            $table->unsignedTinyInteger('pending_days')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'year'], 'employee_year_unique');
            $table->index('year');
        });

        // Agregar columna vacation_balance_id a la tabla vacations
        Schema::table('vacations', function (Blueprint $table) {
            $table->foreignId('vacation_balance_id')->nullable()->after('employee_id')->constrained('employee_vacation_balances')->nullOnDelete();
            $table->unsignedTinyInteger('business_days')->nullable()->after('days_requested');
            $table->date('return_date')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropForeign(['vacation_balance_id']);
            $table->dropColumn(['vacation_balance_id', 'business_days', 'return_date']);
        });

        Schema::dropIfExists('employee_vacation_balances');
    }
};
