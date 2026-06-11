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
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->longText('intro_text')->nullable()->after('body');
            $table->longText('closing_text')->nullable()->after('intro_text');
            $table->text('signature_notes')->nullable()->after('closing_text');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contract_templates', function (Blueprint $table) {
            $table->dropColumn(['intro_text', 'closing_text', 'signature_notes']);
        });
    }
};
