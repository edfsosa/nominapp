<?php

namespace Database\Seeders;

use App\Models\Advance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra préstamos y adelantos salariales de ejemplo.
 *
 * Genera tres registros representativos:
 *   - Préstamo activo con cuotas parcialmente pagadas.
 *   - Adelanto salarial pendiente de aprobación.
 *   - Préstamo ya pagado en su totalidad.
 *
 * Se asignan a los tres primeros empleados activos disponibles.
 */
class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(3)->get();
        $adminId = DB::table('users')->value('id');

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar préstamos.');

            return;
        }

        DB::transaction(function () use ($employees, $adminId) {
            $now = now();
            $e = $employees->values(); // índices 0, 1, 2 garantizados

            $loans = [];

            // Préstamo activo — otorgado hace 3 meses, 3 cuotas pagadas, 7 pendientes
            if ($e->has(0)) {
                $loans[] = [
                    'employee_id' => $e[0]->id,
                    'amount' => 5000000,
                    'interest_rate' => 0,
                    'installments_count' => 10,
                    'installment_amount' => 500000,
                    'outstanding_balance' => 3500000,
                    'status' => 'active',
                    'reason' => 'Préstamo personal para gastos médicos',
                    'notes' => null,
                    'granted_at' => Carbon::now()->subMonths(3)->toDateString(),
                    'granted_by_id' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Préstamo cancelado — otorgado hace 6 meses, todas las cuotas pagadas
            if ($e->has(2)) {
                $loans[] = [
                    'employee_id' => $e[2]->id,
                    'amount' => 2000000,
                    'interest_rate' => 0,
                    'installments_count' => 4,
                    'installment_amount' => 500000,
                    'outstanding_balance' => 0,
                    'status' => 'paid',
                    'reason' => 'Préstamo para gastos de estudios',
                    'notes' => null,
                    'granted_at' => Carbon::now()->subMonths(6)->toDateString(),
                    'granted_by_id' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (! empty($loans)) {
                DB::table('loans')->insert($loans);
            }

            $loanIds = DB::table('loans')
                ->whereIn('employee_id', $employees->pluck('id'))
                ->orderBy('id')
                ->pluck('id')
                ->values();

            $installments = [];

            // Cuotas del préstamo activo (índice 0): 3 pagadas, 7 pendientes
            if ($loanIds->has(0)) {
                $baseDate = Carbon::now()->subMonths(3);
                for ($i = 1; $i <= 10; $i++) {
                    $dueDate = $baseDate->copy()->addMonths($i);
                    $paid = $i <= 3;

                    $installments[] = [
                        'loan_id' => $loanIds[0],
                        'installment_number' => $i,
                        'amount' => 500000,
                        'capital_amount' => 500000,
                        'interest_amount' => 0,
                        'due_date' => $dueDate->toDateString(),
                        'status' => $paid ? 'paid' : 'pending',
                        'paid_at' => $paid ? $dueDate->toDateTimeString() : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            // Cuotas del préstamo pagado (índice 1 en loanIds, préstamo e[2]): todas pagadas
            if ($loanIds->has(1)) {
                $baseDate = Carbon::now()->subMonths(6);
                for ($i = 1; $i <= 4; $i++) {
                    $dueDate = $baseDate->copy()->addMonths($i);

                    $installments[] = [
                        'loan_id' => $loanIds[1],
                        'installment_number' => $i,
                        'amount' => 500000,
                        'capital_amount' => 500000,
                        'interest_amount' => 0,
                        'due_date' => $dueDate->toDateString(),
                        'status' => 'paid',
                        'paid_at' => $dueDate->toDateTimeString(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if ($installments) {
                DB::table('loan_installments')->insert($installments);
            }

            // Adelanto salarial pendiente para el segundo empleado
            if ($e->has(1)) {
                Advance::create([
                    'employee_id' => $e[1]->id,
                    'amount' => 1500000,
                    'status' => 'pending',
                    'notes' => 'Adelanto de salario — gastos de mudanza',
                ]);
            }
        });
    }
}
