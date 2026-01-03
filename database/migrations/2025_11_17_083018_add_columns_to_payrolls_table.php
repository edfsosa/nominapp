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
        Schema::table('payrolls', function (Blueprint $table) {
            $table->decimal('base_salary', 12, 2)->after('payroll_period_id'); // salario base que se obtiene del empleado
            $table->string('pdf_path')->nullable(); // para guardar el archivo
            $table->timestamp('generated_at')->nullable()->after('pdf_path'); // para saber cuándo se generó la nómina
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['base_salary', 'pdf_path', 'generated_at']);
        });
    }
};
