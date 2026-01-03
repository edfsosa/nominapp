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
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_day_id')->constrained('attendance_days')->onDelete('cascade');
            $table->enum('event_type', ['check_in', 'break_start', 'break_end', 'check_out']);
            $table->json('location')->nullable(); // Ej: lat,lng o texto
            $table->dateTime('recorded_at')->useCurrent();
            $table->timestamps();
            $table->index(['attendance_day_id', 'event_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_events');
    }
};
