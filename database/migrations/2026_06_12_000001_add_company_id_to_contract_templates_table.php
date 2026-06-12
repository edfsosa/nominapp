<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Agrega company_id a contract_templates y actualiza el índice único a (company_id, type). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('companies')->after('id');
        });

        // Asignar filas existentes a la primera empresa activa
        DB::table('contract_templates')->whereNull('company_id')->update([
            'company_id' => DB::table('companies')->where('is_active', 1)->orderBy('id')->value('id'),
        ]);

        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropUnique(['type']);
            $table->unique(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'type']);
            $table->unique(['type']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
