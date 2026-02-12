<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(3)->get();
        $adminId = DB::table('users')->where('email', 'admin@example.com')->value('id');

        if ($employees->count() < 3) {
            $this->command->warn('Se necesitan al menos 3 empleados activos para LoanSeeder.');
            return;
        }

        DB::transaction(function () use ($employees, $adminId) {
            $now = now();

            $loans = [
                // Préstamo activo con cuotas pendientes
                [
                    'employee_id'        => $employees[0]->id,
                    'type'               => 'loan',
                    'amount'             => 5000000,
                    'installments_count' => 10,
                    'installment_amount' => 500000,
                    'status'             => 'active',
                    'reason'             => 'Préstamo personal para gastos médicos',
                    'granted_at'         => Carbon::now()->subMonths(3)->toDateString(),
                    'granted_by_id'      => $adminId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ],
                // Anticipo salarial pendiente de aprobación
                [
                    'employee_id'        => $employees[1]->id,
                    'type'               => 'advance',
                    'amount'             => 1500000,
                    'installments_count' => 3,
                    'installment_amount' => 500000,
                    'status'             => 'pending',
                    'reason'             => 'Anticipo de salario',
                    'granted_at'         => null,
                    'granted_by_id'      => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ],
                // Préstamo ya pagado
                [
                    'employee_id'        => $employees[2]->id,
                    'type'               => 'loan',
                    'amount'             => 2000000,
                    'installments_count' => 4,
                    'installment_amount' => 500000,
                    'status'             => 'paid',
                    'reason'             => 'Préstamo para estudios',
                    'granted_at'         => Carbon::now()->subMonths(6)->toDateString(),
                    'granted_by_id'      => $adminId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ],
            ];

            DB::table('loans')->insert($loans);

            // Obtener IDs de los préstamos insertados
            $loanIds = DB::table('loans')
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderBy('id')
                ->pluck('id')
                ->toArray();

            $installments = [];

            // Cuotas del préstamo activo (3 pagadas, 7 pendientes)
            $baseDate = Carbon::now()->subMonths(3);
            for ($i = 1; $i <= 10; $i++) {
                $dueDate = $baseDate->copy()->addMonths($i);
                $isPaid = $i <= 3;

                $installments[] = [
                    'loan_id'            => $loanIds[0],
                    'installment_number' => $i,
                    'amount'             => 500000,
                    'due_date'           => $dueDate->toDateString(),
                    'status'             => $isPaid ? 'paid' : 'pending',
                    'paid_at'            => $isPaid ? $dueDate->toDateTimeString() : null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            // Cuotas del préstamo pagado (todas pagadas)
            $baseDate = Carbon::now()->subMonths(6);
            for ($i = 1; $i <= 4; $i++) {
                $dueDate = $baseDate->copy()->addMonths($i);

                $installments[] = [
                    'loan_id'            => $loanIds[2],
                    'installment_number' => $i,
                    'amount'             => 500000,
                    'due_date'           => $dueDate->toDateString(),
                    'status'             => 'paid',
                    'paid_at'            => $dueDate->toDateTimeString(),
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            if ($installments) {
                DB::table('loan_installments')->insert($installments);
            }
        });
    }
}
