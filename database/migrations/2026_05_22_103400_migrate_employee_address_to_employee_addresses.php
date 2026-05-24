<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->get(['id', 'address'])
            ->each(function (object $employee): void {
                DB::table('employee_addresses')->insert([
                    'employee_id' => $employee->id,
                    'type' => 'principal',
                    'street' => $employee->address,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('employees', function (Blueprint $table): void {
            $table->dropColumn('address');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('address')->nullable()->after('nationality');
        });

        DB::table('employee_addresses')
            ->where('type', 'principal')
            ->get(['employee_id', 'street'])
            ->each(function (object $address): void {
                DB::table('employees')
                    ->where('id', $address->employee_id)
                    ->update(['address' => $address->street]);
            });
    }
};
