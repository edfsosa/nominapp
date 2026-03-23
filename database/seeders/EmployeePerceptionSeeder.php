<?php

namespace Database\Seeders;

use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra percepciones asignadas a empleados activos según su cargo y departamento.
 *
 * - BON001 (Bonificación por Desempeño): todos los empleados activos.
 * - COM001 (Comisión por Ventas): solo empleados de Ventas o Atención al Cliente.
 * - GRA001 (Gratificación Anual): solo empleados con cargo gerencial o de supervisión.
 */
class EmployeePerceptionSeeder extends Seeder
{
    /** Departamentos que reciben comisión por ventas. */
    private const SALES_DEPARTMENTS = ['Ventas', 'Atención al Cliente'];

    /** Palabras clave en el nombre del cargo que califican para gratificación anual. */
    private const MANAGEMENT_KEYWORDS = ['Gerente', 'Supervisor', 'Jefe', 'Coordinador', 'Administrador'];

    public function run(): void
    {
        $employees   = Employee::where('status', 'active')->with('activeContract.department')->get();
        $perceptions = DB::table('perceptions')->where('is_active', true)->get()->keyBy('code');

        if ($employees->isEmpty() || $perceptions->isEmpty()) {
            $this->command->warn('Se necesitan empleados activos y percepciones para este seeder.');
            return;
        }

        $yearStart = Carbon::create((int) date('Y'), 1, 1)->toDateString();
        $now       = now();
        $rows      = [];

        foreach ($employees as $employee) {
            $contractStart   = $employee->activeContract?->start_date?->toDateString();
            $startDate       = $contractStart && $contractStart > $yearStart ? $contractStart : $yearStart;
            $departmentName  = $employee->activeContract?->department?->name ?? '';
            $positionName    = $employee->activeContract?->position?->name ?? '';

            // BON001 — Bonificación por Desempeño: todos los empleados activos
            if ($perceptions->has('BON001')) {
                $rows[] = $this->row($employee->id, $perceptions['BON001']->id, $startDate, null, $now);
            }

            // COM001 — Comisión por Ventas: solo Ventas y Atención al Cliente
            if ($perceptions->has('COM001') && in_array($departmentName, self::SALES_DEPARTMENTS)) {
                $rows[] = $this->row($employee->id, $perceptions['COM001']->id, $startDate, null, $now, 'Comisión estándar del departamento');
            }

            // GRA001 — Gratificación Anual: solo cargos gerenciales/supervisión
            if ($perceptions->has('GRA001') && $this->isManagementPosition($positionName)) {
                $rows[] = $this->row($employee->id, $perceptions['GRA001']->id, $startDate, null, $now);
            }
        }

        if ($rows) {
            DB::table('employee_perceptions')->insert($rows);
        }
    }

    /**
     * Construye una fila para employee_perceptions.
     *
     * @param  \Carbon\Carbon $now
     * @return array<string, mixed>
     */
    private function row(int $employeeId, int $perceptionId, string $startDate, ?string $endDate, Carbon $now, ?string $notes = null): array
    {
        return [
            'employee_id'          => $employeeId,
            'perception_id'        => $perceptionId,
            'start_date'           => $startDate,
            'end_date'             => $endDate,
            'custom_amount'        => null,
            'notes'                => $notes,
            'deactivated_by_system'=> false,
            'created_at'           => $now,
            'updated_at'           => $now,
        ];
    }

    /**
     * Indica si el nombre del cargo corresponde a una posición gerencial o de supervisión.
     */
    private function isManagementPosition(string $positionName): bool
    {
        foreach (self::MANAGEMENT_KEYWORDS as $keyword) {
            if (str_contains($positionName, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
