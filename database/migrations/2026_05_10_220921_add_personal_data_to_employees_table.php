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
        Schema::table('employees', function (Blueprint $table) {
            $table->string('marital_status')->nullable()->after('gender');
            $table->string('nationality')->nullable()->default('Paraguaya')->after('marital_status');
            $table->string('address')->nullable()->after('nationality');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['marital_status', 'nationality', 'address']);
        });
    }
};
