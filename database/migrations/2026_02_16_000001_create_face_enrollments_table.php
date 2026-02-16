<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->json('face_descriptor')->nullable();
            $table->enum('status', [
                'pending_capture',
                'pending_approval',
                'approved',
                'rejected',
                'expired',
            ])->default('pending_capture');
            $table->timestamp('expires_at');
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('generated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_enrollments');
    }
};
