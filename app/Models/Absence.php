<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_day_id',
        'status',
        'reason',
        'reported_at',
        'reported_by_id',
        'reviewed_at',
        'reviewed_by_id',
        'review_notes',
        'documents',
        'employee_deduction_id',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'documents' => 'array',
    ];

    /**
     * Relación con el empleado
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Relación con el día de asistencia
     */
    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    /**
     * Usuario que reportó la ausencia
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_id');
    }

    /**
     * Usuario que revisó la ausencia
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Deducción generada por ausencia injustificada
     */
    public function employeeDeduction(): BelongsTo
    {
        return $this->belongsTo(EmployeeDeduction::class);
    }

    /**
     * Obtiene el label traducido del estado
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'justified' => 'Justificada',
            'unjustified' => 'Injustificada',
            default => 'Desconocido',
        };
    }

    /**
     * Obtiene el color del badge según el estado
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'justified' => 'success',
            'unjustified' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Obtiene el icono según el estado
     */
    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending' => 'heroicon-o-clock',
            'justified' => 'heroicon-o-check-circle',
            'unjustified' => 'heroicon-o-x-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene todas las opciones de estado para selects
     */
    public static function getStatusOptions(): array
    {
        return [
            'pending' => 'Pendiente',
            'justified' => 'Justificada',
            'unjustified' => 'Injustificada',
        ];
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para obtener solo ausencias pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para obtener solo ausencias justificadas
     */
    public function scopeJustified($query)
    {
        return $query->where('status', 'justified');
    }

    /**
     * Scope para obtener solo ausencias injustificadas
     */
    public function scopeUnjustified($query)
    {
        return $query->where('status', 'unjustified');
    }

    /**
     * Scope para filtrar por empleado
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Verifica si la ausencia está pendiente de revisión
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si la ausencia fue justificada
     */
    public function isJustified(): bool
    {
        return $this->status === 'justified';
    }

    /**
     * Verifica si la ausencia fue marcada como injustificada
     */
    public function isUnjustified(): bool
    {
        return $this->status === 'unjustified';
    }

    /**
     * Verifica si la ausencia ya generó una deducción
     */
    public function hasDeduction(): bool
    {
        return !is_null($this->employee_deduction_id);
    }

    /**
     * Obtiene el atributo status_in_spanish
     */
    public function getStatusInSpanishAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    /**
     * Marca la ausencia como justificada
     *
     * @param int $reviewedById ID del usuario que revisa
     * @param string|null $reviewNotes Notas de revisión
     * @return array Resultado con 'success' y 'message'
     */
    public function justify(int $reviewedById, ?string $reviewNotes = null): array
    {
        $wasUnjustified = $this->isUnjustified();

        // Si tenía una deducción previa, eliminarla
        if ($wasUnjustified && $this->employee_deduction_id) {
            $deduction = EmployeeDeduction::find($this->employee_deduction_id);
            if ($deduction) {
                $deduction->delete();
            }
        }

        // Actualizar el registro de ausencia
        $this->update([
            'status' => 'justified',
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewedById,
            'review_notes' => $reviewNotes ?? $this->review_notes,
            'employee_deduction_id' => null,
        ]);

        // Actualizar attendance_day
        $this->attendanceDay->update([
            'justified_absence' => true,
        ]);

        $message = $wasUnjustified
            ? 'La ausencia ha sido cambiada a justificada y la deducción fue eliminada.'
            : 'La ausencia ha sido marcada como justificada.';

        return [
            'success' => true,
            'message' => $message,
        ];
    }

    /**
     * Marca la ausencia como injustificada y genera la deducción correspondiente
     *
     * @param int $reviewedById ID del usuario que revisa
     * @param string $reviewNotes Notas de revisión (requerido)
     * @return array Resultado con 'success', 'message' y 'deduction_amount'
     */
    public function markAsUnjustified(int $reviewedById, string $reviewNotes): array
    {
        $wasJustified = $this->isJustified();

        // Actualizar el registro de ausencia
        $this->update([
            'status' => 'unjustified',
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewedById,
            'review_notes' => $reviewNotes,
        ]);

        // Actualizar attendance_day
        $this->attendanceDay->update([
            'justified_absence' => false,
        ]);

        $deductionAmount = null;
        $employmentTypeLabel = null;

        // Si ya tenía una deducción, no crear otra
        if (!$this->employee_deduction_id) {
            // Buscar o crear la deducción por ausencias
            $deduction = Deduction::firstOrCreate(
                ['code' => 'AUS-INJ'],
                [
                    'name' => 'Ausencia Injustificada',
                    'description' => 'Deducción automática por ausencia injustificada',
                    'calculation' => 'fixed',
                    'is_mandatory' => true,
                    'is_active' => true,
                    'affects_ips' => false,
                    'affects_irp' => false,
                ]
            );

            // Calcular el monto de deducción según el tipo de empleo
            $employee = $this->employee;
            $deductionAmount = $employee->getAbsenceDeductionAmount();

            $employeeDeduction = EmployeeDeduction::create([
                'employee_id' => $employee->id,
                'deduction_id' => $deduction->id,
                'start_date' => $this->attendanceDay->date,
                'end_date' => $this->attendanceDay->date,
                'custom_amount' => $deductionAmount,
                'notes' => "Deducción automática por ausencia injustificada del {$this->attendanceDay->date->format('d/m/Y')}",
            ]);

            // Vincular la deducción con la ausencia
            $this->update([
                'employee_deduction_id' => $employeeDeduction->id,
            ]);

            $employmentTypeLabel = $employee->employment_type === 'day_laborer' ? 'jornalero' : 'tiempo completo';
            $message = "Se generó una deducción de " . number_format($deductionAmount, 0, ',', '.') . " Gs. (empleado {$employmentTypeLabel}).";
        } else {
            $message = $wasJustified
                ? "Se cambió el estado a injustificada. La deducción ya existe."
                : "Se marcó como injustificada.";
        }

        return [
            'success' => true,
            'message' => $message,
            'deduction_amount' => $deductionAmount,
            'employment_type_label' => $employmentTypeLabel,
        ];
    }
}
