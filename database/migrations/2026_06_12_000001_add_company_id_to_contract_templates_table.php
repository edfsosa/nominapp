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

        // Capturar el contenido de las plantillas antes de asignar company_id
        $existingTemplates = DB::table('contract_templates')->whereNull('company_id')->get();
        $companies = DB::table('companies')->where('is_active', 1)->orderBy('id')->pluck('id');

        if ($companies->isNotEmpty() && $existingTemplates->isNotEmpty()) {
            // Asignar las filas originales a la primera empresa
            DB::table('contract_templates')
                ->whereNull('company_id')
                ->update(['company_id' => $companies->first()]);

            // Copiar cada plantilla a las demás empresas activas
            $now = now();
            foreach ($companies->skip(1) as $companyId) {
                foreach ($existingTemplates as $template) {
                    DB::table('contract_templates')->insert([
                        'company_id'      => $companyId,
                        'type'            => $template->type,
                        'body'            => $template->body,
                        'intro_text'      => $template->intro_text ?? null,
                        'closing_text'    => $template->closing_text ?? null,
                        'signature_notes' => $template->signature_notes ?? null,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ]);
                }
            }
        }

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
