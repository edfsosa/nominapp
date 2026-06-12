<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Agrega campos de presentación visual al PDF del contrato: título, firmas, encabezado y pie. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->string('document_title')->nullable()->after('signature_notes');
            $table->string('document_subtitle')->nullable()->after('document_title');
            $table->string('document_art_reference')->nullable()->after('document_subtitle');
            $table->string('signature_employee_label')->nullable()->after('document_art_reference');
            $table->string('signature_employer_label')->nullable()->after('signature_employee_label');
            $table->string('signature_employer_sublabel')->nullable()->after('signature_employer_label');
            $table->boolean('show_header')->default(true)->after('signature_employer_sublabel');
            $table->boolean('show_footer')->default(true)->after('show_header');
        });
    }

    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn([
                'document_title',
                'document_subtitle',
                'document_art_reference',
                'signature_employee_label',
                'signature_employer_label',
                'signature_employer_sublabel',
                'show_header',
                'show_footer',
            ]);
        });
    }
};
