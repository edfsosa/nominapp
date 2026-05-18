<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra adelantos salariales de ejemplo con todos los estados del ciclo de vida.
 *
 * Crea un adelanto por cada estado relevante (pending, approved, disbursed,
 * rejected, cancelled) y las cuentas bancarias necesarias para los adelantos
 * con método de pago 'transfer'.
 *
 * Se asigna a los empleados en índices 1–5 para no solapar con LoanSeeder (índice 0).
 */
class AdvanceSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(6)->get()->values();
        $adminId = DB::table('users')->value('id');
        $now = now();

        if ($employees->count() < 6) {
            $this->command->warn('Se necesitan al menos 6 empleados activos para el AdvanceSeeder.');

            return;
        }

        // ── Cuentas bancarias para empleados con adelanto por transferencia ──────
        $bankAccounts = [];

        foreach ([1, 2, 3] as $idx) {
            $emp = $employees[$idx];

            $exists = DB::table('employee_bank_accounts')
                ->where('employee_id', $emp->id)
                ->where('is_primary', true)
                ->exists();

            if (! $exists) {
                $bankAccounts[] = [
                    'employee_id' => $emp->id,
                    'bank' => 'itau',
                    'account_type' => 'corriente',
                    'account_number' => '1000'.str_pad($emp->ci, 6, '0', STR_PAD_LEFT),
                    'holder_name' => $emp->first_name.' '.$emp->last_name,
                    'holder_ci' => $emp->ci,
                    'is_primary' => true,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! empty($bankAccounts)) {
            DB::table('employee_bank_accounts')->insert($bankAccounts);
        }

        // ── Adelantos ─────────────────────────────────────────────────────────────
        DB::table('advances')->insert([
            // 1. Pendiente de aprobación — transfer
            [
                'employee_id' => $employees[1]->id,
                'amount' => 800_000,
                'status' => 'pending',
                'payment_method' => 'transfer',
                'notes' => 'Gastos de emergencia médica',
                'approved_by_id' => null,
                'approved_at' => null,
                'disbursed_at' => null,
                'employee_deduction_id' => null,
                'payroll_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // 2. Aprobado, dinero aún no entregado — transfer
            [
                'employee_id' => $employees[2]->id,
                'amount' => 600_000,
                'status' => 'approved',
                'payment_method' => 'transfer',
                'notes' => 'Reparación de vehículo',
                'approved_by_id' => $adminId,
                'approved_at' => Carbon::now()->subDays(3)->toDateTimeString(),
                'disbursed_at' => null,
                'employee_deduction_id' => null,
                'payroll_id' => null,
                'created_at' => Carbon::now()->subDays(4)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(3)->toDateTimeString(),
            ],

            // 3. Entregado al empleado, pendiente de descuento en nómina — transfer
            [
                'employee_id' => $employees[3]->id,
                'amount' => 500_000,
                'status' => 'disbursed',
                'payment_method' => 'transfer',
                'notes' => 'Pago de deuda bancaria',
                'approved_by_id' => $adminId,
                'approved_at' => Carbon::now()->subDays(7)->toDateTimeString(),
                'disbursed_at' => '2026-05-13',
                'employee_deduction_id' => null,
                'payroll_id' => null,
                'created_at' => Carbon::now()->subDays(8)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(1)->toDateTimeString(),
            ],

            // 4. Rechazado — cash
            [
                'employee_id' => $employees[4]->id,
                'amount' => 400_000,
                'status' => 'rejected',
                'payment_method' => 'cash',
                'notes' => 'Monto supera el límite permitido por política interna',
                'approved_by_id' => null,
                'approved_at' => null,
                'disbursed_at' => null,
                'employee_deduction_id' => null,
                'payroll_id' => null,
                'created_at' => Carbon::now()->subDays(5)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(5)->toDateTimeString(),
            ],

            // 5. Cancelado a pedido del empleado — cash
            [
                'employee_id' => $employees[5]->id,
                'amount' => 300_000,
                'status' => 'cancelled',
                'payment_method' => 'cash',
                'notes' => 'Cancelado a pedido del empleado',
                'approved_by_id' => null,
                'approved_at' => null,
                'disbursed_at' => null,
                'employee_deduction_id' => null,
                'payroll_id' => null,
                'created_at' => Carbon::now()->subDays(10)->toDateTimeString(),
                'updated_at' => Carbon::now()->subDays(9)->toDateTimeString(),
            ],
        ]);
    }
}
