<?php

namespace Database\Seeders;

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
 *   - Préstamo ya cancelado en su totalidad.
 *
 * Se asignan a los tres primeros empleados activos disponibles.
 */
class LoanSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(3)->get();
        $adminId   = DB::table('users')->value('id');

        if ($employees->isEmpty()) {
            $this->command->warn('No hay empleados activos para generar préstamos.');
            return;
        }

        DB::transaction(function () use ($employees, $adminId) {
            $now  = now();
            $e    = $employees->values(); // índices 0, 1, 2 garantizados

            $loans = [];

            // Préstamo activo — otorgado hace 3 meses, 3 cuotas pagadas, 7 pendientes
            if ($e->has(0)) {
                $loans[] = [
                    'employee_id'        => $e[0]->id,
                    'type'               => 'loan',
                    'amount'             => 5000000,
                    'installments_count' => 10,
                    'installment_amount' => 500000,
                    'status'             => 'active',
                    'reason'             => 'Préstamo personal para gastos médicos',
                    'notes'              => null,
                    'granted_at'         => Carbon::now()->subMonths(3)->toDateString(),
                    'granted_by_id'      => $adminId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            // Adelanto salarial — solicitado, aún no aprobado
            if ($e->has(1)) {
                $loans[] = [
                    'employee_id'        => $e[1]->id,
                    'type'               => 'advance',
                    'amount'             => 1500000,
                    'installments_count' => 3,
                    'installment_amount' => 500000,
                    'status'             => 'pending',
                    'reason'             => 'Adelanto de salario — gastos de mudanza',
                    'notes'              => null,
                    'granted_at'         => null,
                    'granted_by_id'      => null,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            // Préstamo cancelado — otorgado hace 6 meses, todas las cuotas pagadas
            if ($e->has(2)) {
                $loans[] = [
                    'employee_id'        => $e[2]->id,
                    'type'               => 'loan',
                    'amount'             => 2000000,
                    'installments_count' => 4,
                    'installment_amount' => 500000,
                    'status'             => 'paid',
                    'reason'             => 'Préstamo para gastos de estudios',
                    'notes'              => null,
                    'granted_at'         => Carbon::now()->subMonths(6)->toDateString(),
                    'granted_by_id'      => $adminId,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            if (empty($loans)) {
                return;
            }

            DB::table('loans')->insert($loans);

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
                    $paid    = $i <= 3;

                    $installments[] = [
                        'loan_id'            => $loanIds[0],
                        'installment_number' => $i,
                        'amount'             => 500000,
                        'due_date'           => $dueDate->toDateString(),
                        'status'             => $paid ? 'paid' : 'pending',
                        'paid_at'            => $paid ? $dueDate->toDateTimeString() : null,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }
            }

            // Cuotas del préstamo pagado (índice 2): todas pagadas
            if ($loanIds->has(2)) {
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
            }

            if ($installments) {
                DB::table('loan_installments')->insert($installments);
            }
        });
    }
}
