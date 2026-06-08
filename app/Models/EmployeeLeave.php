<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

/** Representa una licencia o permiso registrado para un empleado. */
class EmployeeLeave extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'start_time',
        'end_time',
        'generates_deduction',
        'employee_deduction_id',
        'reason',
        'document_path',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'generates_deduction' => 'boolean',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    /** Empleado al que pertenece la licencia. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Ausencias justificadas por esta licencia. */
    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class);
    }

    /** Deducción generada al aprobar un permiso parcial con descuento. */
    public function employeeDeduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Indica si es un permiso parcial (por horas, no por días completos). */
    public function isPartialDay(): bool
    {
        return filled($this->start_time) && filled($this->end_time);
    }

    /**
     * Duración en horas del permiso parcial (fracción decimal).
     * Retorna 0 si no es un permiso parcial o si los tiempos son inválidos.
     */
    public function durationInHours(): float
    {
        if (! $this->isPartialDay()) {
            return 0.0;
        }

        $start = Carbon::parse($this->start_date->format('Y-m-d').' '.$this->start_time);
        $end = Carbon::parse($this->start_date->format('Y-m-d').' '.$this->end_time);

        if ($end->lte($start)) {
            return 0.0;
        }

        return round($start->floatDiffInHours($end), 2);
    }

    /**
     * Monto de la deducción calculado según el salario del contrato activo.
     * Mensual: salario / (30 × 8) × horas
     * Jornalero: salario_diario / 8 × horas
     */
    public function calculatedDeductionAmount(): float
    {
        $hours = $this->durationInHours();
        if ($hours <= 0) {
            return 0.0;
        }

        $contract = $this->employee?->activeContract;
        if (! $contract) {
            return 0.0;
        }

        $salary = (float) $contract->salary;
        if ($salary <= 0) {
            return 0.0;
        }

        $hourlyRate = $contract->salary_type === 'mensual'
            ? $salary / (30 * 8)
            : $salary / 8;

        return round($hourlyRate * $hours, 2);
    }

    // ─── Ciclo de vida ────────────────────────────────────────────────────────

    /**
     * Aprueba la licencia.
     * - Permisos parciales (por horas): si generates_deduction, crea un EmployeeDeduction con LIC001.
     * - Permisos por días completos: justifica automáticamente las ausencias del período.
     *
     * @return array{justified_count: int}
     */
    public function approve(int $approvedById): array
    {
        $updates = ['status' => 'approved'];

        if ($this->generates_deduction && $this->isPartialDay() && ! $this->employee_deduction_id) {
            $deductionRecord = Deduction::where('code', 'LIC001')->first();

            if ($deductionRecord) {
                $amount = $this->calculatedDeductionAmount();
                $typeLabel = self::getTypeOptions()[$this->type] ?? $this->type;
                $period = $this->start_date->format('d/m/Y').' '.$this->start_time.' - '.$this->end_time;

                $employeeDeduction = EmployeeDeduction::create([
                    'employee_id' => $this->employee_id,
                    'deduction_id' => $deductionRecord->id,
                    'start_date' => now()->toDateString(),
                    'end_date' => now()->toDateString(),
                    'custom_amount' => $amount,
                    'notes' => "Permiso parcial: {$typeLabel} ({$period})",
                ]);

                $updates['employee_deduction_id'] = $employeeDeduction->id;
            } else {
                Log::warning('EmployeeLeave::approve — código LIC001 no encontrado en deductions. Descuento no generado.', [
                    'employee_leave_id' => $this->id,
                    'employee_id' => $this->employee_id,
                ]);
            }
        }

        $this->update($updates);

        // Solo justifica ausencias en licencias de día completo
        $justifiedCount = 0;
        if (! $this->isPartialDay()) {
            $absences = Absence::where('employee_id', $this->employee_id)
                ->whereHas('attendanceDay', fn ($q) => $q->whereBetween('date', [$this->start_date, $this->end_date]))
                ->whereIn('status', ['pending', 'unjustified'])
                ->get();

            $typeLabel = self::getTypeOptions()[$this->type] ?? $this->type;
            $period = $this->start_date->format('d/m/Y').' al '.$this->end_date->format('d/m/Y');

            foreach ($absences as $absence) {
                $absence->justify(
                    $approvedById,
                    "Justificada por licencia: {$typeLabel} ({$period})",
                    $this->id
                );
            }

            $justifiedCount = $absences->count();
        }

        return ['justified_count' => $justifiedCount];
    }

    /** Rechaza la licencia. */
    public function reject(): void
    {
        $this->update(['status' => 'rejected']);
    }

    // ─── Opciones estáticas ───────────────────────────────────────────────────

    /**
     * Opciones de tipo de licencia para selects y filtros.
     *
     * @return array<string, string>
     */
    public static function getTypeOptions(): array
    {
        return [
            'medical_leave' => 'Reposo Médico',
            'vacation' => 'Vacaciones',
            'day_off' => 'Día Libre',
            'maternity_leave' => 'Licencia de Maternidad',
            'paternity_leave' => 'Licencia de Paternidad',
            'unpaid_leave' => 'Sin Goce de Sueldo',
            'other' => 'Otro',
        ];
    }

    /**
     * Colores de badge por tipo de licencia.
     *
     * @return array<string, string>
     */
    public static function getTypeColors(): array
    {
        return [
            'medical_leave' => 'danger',
            'vacation' => 'success',
            'day_off' => 'info',
            'maternity_leave' => 'primary',
            'paternity_leave' => 'gray',
            'unpaid_leave' => 'warning',
            'other' => 'gray',
        ];
    }

    /**
     * Íconos por tipo de licencia.
     *
     * @return array<string, string>
     */
    public static function getTypeIcons(): array
    {
        return [
            'medical_leave' => 'heroicon-o-heart',
            'vacation' => 'heroicon-o-sun',
            'day_off' => 'heroicon-o-calendar',
            'maternity_leave' => 'heroicon-o-home',
            'paternity_leave' => 'heroicon-o-home',
            'unpaid_leave' => 'heroicon-o-pause-circle',
            'other' => 'heroicon-o-document-text',
        ];
    }

    /**
     * Opciones de estado para selects y filtros.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'approved' => 'Aprobado',
            'rejected' => 'Rechazado',
        ];
    }

    /**
     * Colores de badge por estado.
     *
     * @return array<string, string>
     */
    public static function getStatusColors(): array
    {
        return [
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
        ];
    }

    /**
     * Íconos por estado.
     *
     * @return array<string, string>
     */
    public static function getStatusIcons(): array
    {
        return [
            'pending' => 'heroicon-o-clock',
            'approved' => 'heroicon-o-check-circle',
            'rejected' => 'heroicon-o-x-circle',
        ];
    }
}
