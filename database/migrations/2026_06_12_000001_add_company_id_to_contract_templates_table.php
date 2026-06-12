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
        // Guard: la columna puede ya existir si un deploy anterior falló a mitad
        if (! Schema::hasColumn('contract_templates', 'company_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->constrained('companies')->after('id');
            });
        }

        // Capturar plantillas sin empresa asignada para copiarlas
        $existingTemplates = DB::table('contract_templates')->whereNull('company_id')->get();
        $companies = DB::table('companies')->where('is_active', 1)->orderBy('id')->pluck('id');

        // Dropar el índice único en type ANTES de los inserts (try/catch para idempotencia en Laravel 12)
        try {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropUnique(['type']);
            });
        } catch (\Throwable $e) {
            // El índice ya fue eliminado en un deploy anterior parcial — continuar
        }

        if ($companies->isNotEmpty() && $existingTemplates->isNotEmpty()) {
            // Asignar las filas originales a la primera empresa (solo las que siguen sin empresa)
            DB::table('contract_templates')
                ->whereNull('company_id')
                ->update(['company_id' => $companies->first()]);

            // Copiar cada plantilla a las demás empresas activas, evitando duplicados
            $now = now();
            foreach ($companies->skip(1) as $companyId) {
                foreach ($existingTemplates as $template) {
                    $exists = DB::table('contract_templates')
                        ->where('company_id', $companyId)
                        ->where('type', $template->type)
                        ->exists();

                    if (! $exists) {
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
        }

        // Agregar índice compuesto (try/catch para idempotencia en caso de deploy parcial anterior)
        try {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->unique(['company_id', 'type']);
            });
        } catch (\Throwable $e) {
            // El índice ya existe por un deploy anterior parcial — continuar
        }
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
