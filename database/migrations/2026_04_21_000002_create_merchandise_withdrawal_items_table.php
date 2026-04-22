<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ítems (productos) de un retiro de mercadería. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchandise_withdrawal_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchandise_withdrawal_id')->constrained()->cascadeOnDelete();
            $table->string('code')->nullable();           // Código interno libre
            $table->string('name');                       // Nombre del producto
            $table->text('description')->nullable();      // Descripción adicional
            $table->decimal('price', 12, 2);              // Precio unitario
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 12, 2);           // price * quantity
            $table->timestamps();

            $table->index('merchandise_withdrawal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchandise_withdrawal_items');
    }
};
