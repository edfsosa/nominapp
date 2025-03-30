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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Relación con User
            $table->integer('ci')->unique()->comment('Cédula de Identidad Paraguaya');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->integer('salary');
            $table->string('department');
            $table->date('hire_date');
            $table->enum('status', ['activo', 'inactivo'])->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
