<?php

use App\Models\Employee;
use App\Services\VacationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Vincula vacaciones históricas (pre-módulo de balances) a sus balances correctos
     * y debita los días usados. Estas vacaciones tienen vacation_balance_id = NULL
     * y business_days = NULL porque fueron creadas antes de que existiera el módulo.
     */
    public function up(): void
    {
        $vacations = DB::table('vacations')
            ->whereNull('vacation_balance_id')
            ->whereIn('status', ['pending', 'approved'])
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();

        foreach ($vacations as $vacation) {
            $employee = Employee::find($vacation->employee_id);
            if (! $employee) {
                continue;
            }

            $startDate = \Carbon\Carbon::parse($vacation->start_date);
            $endDate = \Carbon\Carbon::parse($vacation->end_date);

            $businessDays = VacationService::calculateBusinessDays($employee, $startDate, $endDate);

            if ($businessDays < 1) {
                continue;
            }

            $balance = VacationService::findBalanceToDebit($employee, $businessDays, $startDate->year);

            DB::table('vacations')->where('id', $vacation->id)->update([
                'vacation_balance_id' => $balance->id,
                'business_days' => $businessDays,
            ]);

            if ($vacation->status === 'approved') {
                DB::table('employee_vacation_balances')
                    ->where('id', $balance->id)
                    ->increment('used_days', $businessDays);
            } else {
                DB::table('employee_vacation_balances')
                    ->where('id', $balance->id)
                    ->increment('pending_days', $businessDays);
            }
        }
    }

    public function down(): void
    {
        // No reversible — sería necesario guardar el estado anterior
    }
};
