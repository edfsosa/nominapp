<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Agrega columna source a attendance_events para distinguir el canal de marcación. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->enum('source', ['terminal', 'mobile', 'manual'])
                ->default('manual')
                ->after('recorded_at')
                ->comment('Canal de marcación: terminal (kiosco), mobile (app móvil), manual (admin)');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_events', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
