<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('maternity_protection_until')->nullable()->after('birth_date')
                ->comment('Fecha hasta la que la empleada goza de protección de maternidad (Ley 5508/15 — 1 año desde el nacimiento del hijo)');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('maternity_protection_until');
        });
    }
};
