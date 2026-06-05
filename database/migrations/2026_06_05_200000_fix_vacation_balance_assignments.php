<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Corrige registros donde used_days > entitled_days en balances de vacaciones.
     *
     * El bug original asignaba vacation_balance_id al año de start_date incluso
     * cuando ese balance tenía entitled_days = 0 y los días disponibles estaban
     * en el balance de un año anterior. El fix reasigna cada vacación al balance
     * correcto (FIFO: más antiguo con capacidad) y recalcula ambos balances.
     */
    public function up(): void
    {
        $badBalances = DB::table('employee_vacation_balances')
            ->whereColumn('used_days', '>', 'entitled_days')
            ->orWhereColumn('pending_days', '>', 'entitled_days')
            ->get();

        foreach ($badBalances as $balance) {
            $vacations = DB::table('vacations')
                ->where('vacation_balance_id', $balance->id)
                ->whereIn('status', ['pending', 'approved'])
                ->orderBy('start_date')
                ->get();

            foreach ($vacations as $vacation) {
                $olderBalances = DB::table('employee_vacation_balances')
                    ->where('employee_id', $balance->employee_id)
                    ->where('id', '!=', $balance->id)
                    ->orderBy('year')
                    ->get();

                foreach ($olderBalances as $candidate) {
                    $available = $candidate->entitled_days - $candidate->used_days - $candidate->pending_days;
                    if ($available >= $vacation->business_days) {
                        DB::table('vacations')
                            ->where('id', $vacation->id)
                            ->update(['vacation_balance_id' => $candidate->id]);
                        break;
                    }
                }
            }

            // Recalculate all balances for this employee from scratch
            $allBalances = DB::table('employee_vacation_balances')
                ->where('employee_id', $balance->employee_id)
                ->get();

            foreach ($allBalances as $b) {
                $usedDays = (int) DB::table('vacations')
                    ->where('vacation_balance_id', $b->id)
                    ->where('status', 'approved')
                    ->sum('business_days');

                $pendingDays = (int) DB::table('vacations')
                    ->where('vacation_balance_id', $b->id)
                    ->where('status', 'pending')
                    ->sum('business_days');

                DB::table('employee_vacation_balances')
                    ->where('id', $b->id)
                    ->update(['used_days' => $usedDays, 'pending_days' => $pendingDays]);
            }
        }
    }

    public function down(): void
    {
        // Data migration — not reversible
    }
};
