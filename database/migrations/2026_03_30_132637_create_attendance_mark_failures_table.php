<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_mark_failures', function (Blueprint $table) {
            $table->id();
            $table->enum('mode', ['terminal', 'mobile', 'unknown'])->default('unknown');
            $table->string('failure_type', 50);                          // face_no_match, invalid_event_sequence, etc.
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('attempted_event_type', ['check_in', 'break_start', 'break_end', 'check_out'])->nullable();
            $table->string('failure_message');
            $table->json('metadata')->nullable();                        // distancia facial, último evento, etc.
            $table->string('ip_address', 45)->nullable();
            $table->json('location')->nullable();                        // lat/lng si estaba disponible
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index('occurred_at');
            $table->index(['employee_id', 'occurred_at']);
            $table->index(['failure_type', 'occurred_at']);
            $table->index(['mode', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_mark_failures');
    }
};
