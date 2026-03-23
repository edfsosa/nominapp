<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Siembra registros de ausencias a partir de los días con status = 'absent'
 * ya existentes en attendance_days (generados por AttendanceDayWithEventsSeeder).
 *
 * Distribución de estados (determinista, basada en días transcurridos):
 *   - Últimos 7 días   → pending   (aún no revisadas)
 *   - Más de 7 días    → justified / unjustified alternando por employee_id
 *
 * Las ausencias revisadas incluyen reviewed_at, reviewed_by_id y review_notes.
 * El campo employee_deduction_id se deja en null: la deducción por ausencia
 * injustificada se aplica al procesar la nómina, no al registrar la ausencia.
 *
 * Depende de: AttendanceDayWithEventsSeeder (attendance_days con status absent).
 */
class AbsenceSeeder extends Seeder
{
    /** Días recientes que quedan en estado pending sin revisar. */
    private const PENDING_DAYS_WINDOW = 7;

    public function run(): void
    {
        $userId = DB::table('users')->value('id');

        // Traer todos los días ausentes de empleados activos
        $absentDays = DB::table('attendance_days as ad')
            ->join('employees as e', 'e.id', '=', 'ad.employee_id')
            ->where('ad.status', 'absent')
            ->where('ad.is_holiday', false)
            ->where('e.status', 'active')
            ->orderBy('ad.date')
            ->get(['ad.id as attendance_day_id', 'ad.employee_id', 'ad.date']);

        if ($absentDays->isEmpty()) {
            $this->command->warn('No hay días con status absent. Ejecuta AttendanceDayWithEventsSeeder primero.');
            return;
        }

        $now      = now();
        $today    = Carbon::today();
        $rows     = [];

        $pendingCount     = 0;
        $justifiedCount   = 0;
        $unjustifiedCount = 0;

        foreach ($absentDays as $day) {
            $date     = Carbon::parse($day->date);
            $daysAgo  = $date->diffInDays($today);
            $empId    = $day->employee_id;

            if ($daysAgo <= self::PENDING_DAYS_WINDOW) {
                // Ausencia reciente: todavía no fue revisada
                $status      = 'pending';
                $reviewedAt  = null;
                $reviewedBy  = null;
                $reviewNotes = null;
                $reportedAt  = $date->copy()->setTime(8, ($empId + $date->day) % 31)->toDateTimeString();

                $pendingCount++;
            } else {
                // Ausencia más antigua: se alterna justified/unjustified por employee_id
                if ($empId % 2 === 0) {
                    $status      = 'justified';
                    $reviewNotes = $this->justifiedNote($empId, $date);
                    $justifiedCount++;
                } else {
                    $status      = 'unjustified';
                    $reviewNotes = 'No se recibió justificación en el plazo establecido.';
                    $unjustifiedCount++;
                }

                $reportedAt = $date->copy()->setTime(8, 0)->toDateTimeString();
                $reviewedAt = $date->copy()->addDays(2)->setTime(10, 0)->toDateTimeString();
                $reviewedBy = $userId;
            }

            $rows[] = [
                'employee_id'          => $empId,
                'attendance_day_id'    => $day->attendance_day_id,
                'status'               => $status,
                'reason'               => $this->absentReason($empId, $date),
                'reported_at'          => $reportedAt,
                'reported_by_id'       => $userId,
                'reviewed_at'          => $reviewedAt,
                'reviewed_by_id'       => $reviewedBy,
                'review_notes'         => $reviewNotes,
                'documents'            => null,
                'employee_deduction_id'=> null,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('absences')->insert($chunk);
        }

        $total = count($rows);
        $this->command->info(
            "Ausencias sembradas: $total total "
            . "($pendingCount pendientes, $justifiedCount justificadas, $unjustifiedCount injustificadas)."
        );
    }

    /**
     * Retorna un motivo de ausencia genérico determinista basado en employee_id y fecha.
     *
     * @param  int    $employeeId
     * @param  Carbon $date
     * @return string
     */
    private function absentReason(int $employeeId, Carbon $date): string
    {
        $reasons = [
            'Enfermedad — comunicó por teléfono.',
            'Trámite personal — sin comprobante.',
            'Problema de transporte — notificó al supervisor.',
            'Cita médica no programada.',
            'Emergencia familiar.',
            'No se comunicó con el empleador.',
        ];

        return $reasons[($employeeId + $date->day) % count($reasons)];
    }

    /**
     * Retorna una nota de revisión para ausencias justificadas.
     *
     * @param  int    $employeeId
     * @param  Carbon $date
     * @return string
     */
    private function justifiedNote(int $employeeId, Carbon $date): string
    {
        $notes = [
            'Presentó certificado médico dentro del plazo.',
            'Adjuntó constancia del trámite realizado.',
            'Supervisor confirmó la emergencia familiar.',
            'Presentó boleta de atención médica.',
        ];

        return $notes[($employeeId + $date->month) % count($notes)];
    }
}
