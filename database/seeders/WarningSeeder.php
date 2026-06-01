<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra amonestaciones laborales de ejemplo con los tres tipos disponibles.
 *
 * Crea 5 registros distribuidos entre distintos empleados activos:
 *   - Dos verbales (tardanza reiterada, conducta inapropiada)
 *   - Dos escritas (ausencia injustificada, incumplimiento de normas)
 *   - Una grave (desobediencia a superiores)
 *
 * Sin integración con nómina — es un módulo puramente documental.
 */
class WarningSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::where('status', 'active')->take(5)->get()->values();
        $adminId = DB::table('users')->value('id');
        $now = now();

        if ($employees->count() < 5) {
            $this->command->warn('Se necesitan al menos 5 empleados activos para el WarningSeeder.');

            return;
        }

        $warnings = [
            // Verbal — tardanza reiterada
            [
                'employee_id' => $employees[0]->id,
                'type' => 'verbal',
                'reason' => 'tardanza',
                'description' => 'El empleado llegó tarde más de cinco veces en el mes sin justificación válida.',
                'issued_at' => now()->subDays(45)->toDateString(),
                'issued_by_id' => $adminId,
                'notes' => 'Se realizó llamado de atención verbal. Queda registrado a efectos informativos.',
                'document_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Verbal — conducta inapropiada
            [
                'employee_id' => $employees[1]->id,
                'type' => 'verbal',
                'reason' => 'conducta',
                'description' => 'Discusión acalorada con un compañero de trabajo en el área de producción.',
                'issued_at' => now()->subDays(30)->toDateString(),
                'issued_by_id' => $adminId,
                'notes' => null,
                'document_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Escrita — ausencia injustificada
            [
                'employee_id' => $employees[2]->id,
                'type' => 'written',
                'reason' => 'ausencia',
                'description' => 'Ausencia sin aviso ni justificación durante dos días laborales consecutivos.',
                'issued_at' => now()->subDays(20)->toDateString(),
                'issued_by_id' => $adminId,
                'notes' => 'Primera amonestación escrita. Si se repite, se procederá a amonestación grave.',
                'document_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Escrita — incumplimiento de normas
            [
                'employee_id' => $employees[3]->id,
                'type' => 'written',
                'reason' => 'incumplimiento',
                'description' => 'Uso del celular personal durante horas de trabajo en áreas restringidas, en violación al reglamento interno.',
                'issued_at' => now()->subDays(15)->toDateString(),
                'issued_by_id' => $adminId,
                'notes' => null,
                'document_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Grave — desobediencia a superiores
            [
                'employee_id' => $employees[4]->id,
                'type' => 'severe',
                'reason' => 'desobediencia',
                'description' => 'El empleado se negó de forma reiterada e injustificada a cumplir instrucciones directas de su jefe inmediato, afectando el cumplimiento de los objetivos del área.',
                'issued_at' => now()->subDays(7)->toDateString(),
                'issued_by_id' => $adminId,
                'notes' => 'Tercer incidente registrado. Se advierte que un nuevo incidente podrá derivar en la rescisión del contrato.',
                'document_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('warnings')->insert($warnings);
    }
}
