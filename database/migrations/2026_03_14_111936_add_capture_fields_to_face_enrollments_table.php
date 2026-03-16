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
        Schema::table('face_enrollments', function (Blueprint $table) {
            // token y expires_at son opcionales en capturas de admin (sin enlace temporal)
            $table->string('token', 64)->nullable()->change();
            $table->timestamp('expires_at')->nullable()->change();

            // Metadatos de la captura
            $table->string('snapshot_path')->nullable()->after('face_descriptor');
            $table->tinyInteger('samples_count')->unsigned()->nullable()->after('snapshot_path');
            $table->decimal('face_score', 5, 4)->unsigned()->nullable()->after('samples_count');

            // Origen de la captura
            $table->enum('source', ['admin', 'self_enrollment'])
                ->default('self_enrollment')
                ->after('face_score');
        });
    }

    public function down(): void
    {
        Schema::table('face_enrollments', function (Blueprint $table) {
            $table->string('token', 64)->nullable(false)->change();
            $table->timestamp('expires_at')->nullable(false)->change();
            $table->dropColumn(['snapshot_path', 'samples_count', 'face_score', 'source']);
        });
    }
};
