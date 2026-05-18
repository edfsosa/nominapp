<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mueve max_loan_amount del grupo general al grupo payroll.
 * Limpia además working_hours_per_week (eliminado de GeneralSettings).
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = DB::table('settings')
            ->where('group', 'general')
            ->where('name', 'max_loan_amount')
            ->value('payload');

        DB::table('settings')->upsert(
            [
                'group'   => 'payroll',
                'name'    => 'max_loan_amount',
                'locked'  => false,
                'payload' => $current ?? '5000000',
            ],
            uniqueBy: ['group', 'name'],
            update: ['payload']
        );

        DB::table('settings')
            ->where('group', 'general')
            ->whereIn('name', ['max_loan_amount', 'working_hours_per_week'])
            ->delete();
    }

    public function down(): void
    {
        $current = DB::table('settings')
            ->where('group', 'payroll')
            ->where('name', 'max_loan_amount')
            ->value('payload');

        DB::table('settings')->upsert(
            [
                'group'   => 'general',
                'name'    => 'max_loan_amount',
                'locked'  => false,
                'payload' => $current ?? '5000000',
            ],
            uniqueBy: ['group', 'name'],
            update: ['payload']
        );

        DB::table('settings')
            ->where('group', 'payroll')
            ->where('name', 'max_loan_amount')
            ->delete();

        DB::table('settings')->upsert(
            [
                'group'   => 'general',
                'name'    => 'working_hours_per_week',
                'locked'  => false,
                'payload' => '48',
            ],
            uniqueBy: ['group', 'name'],
            update: ['payload']
        );
    }
};
