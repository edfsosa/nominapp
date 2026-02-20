<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FaceEnrollment extends Model
{
    protected $fillable = [
        'employee_id',
        'token',
        'face_descriptor',
        'status',
        'expires_at',
        'captured_at',
        'reviewed_at',
        'generated_by_id',
        'reviewed_by_id',
        'review_notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'face_descriptor' => 'array',
        'expires_at' => 'datetime',
        'captured_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'generated_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopePendingCapture($query)
    {
        return $query->where('status', 'pending_capture');
    }

    public function scopeValid($query)
    {
        return $query->where('status', 'pending_capture')
            ->where('expires_at', '>', now());
    }

    // =========================================================================
    // ESTADO
    // =========================================================================

    public function isPendingCapture(): bool
    {
        return $this->status === 'pending_capture';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->isPendingCapture() && !$this->isExpired();
    }

    // =========================================================================
    // LABELS, COLORES E ICONOS DE ESTADO
    // =========================================================================

    public static function getStatusOptions(): array
    {
        return [
            'pending_capture'  => 'Pendiente de Captura',
            'pending_approval' => 'Pendiente de Aprobación',
            'approved'         => 'Aprobado',
            'rejected'         => 'Rechazado',
            'expired'          => 'Expirado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'pending_capture'  => 'gray',
            'pending_approval' => 'warning',
            'approved'         => 'success',
            'rejected'         => 'danger',
            'expired'          => 'gray',
            default            => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'pending_capture'  => 'heroicon-o-camera',
            'pending_approval' => 'heroicon-o-clock',
            'approved'         => 'heroicon-o-check-circle',
            'rejected'         => 'heroicon-o-x-circle',
            'expired'          => 'heroicon-o-exclamation-triangle',
            default            => 'heroicon-o-question-mark-circle',
        };
    }

    // =========================================================================
    // MÉTODOS DE NEGOCIO
    // =========================================================================

    /**
     * Aprueba el registro facial: copia el descriptor al empleado y expira otros enrollments pendientes.
     */
    public function approve(int $reviewedById, ?string $notes = null): array
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewedById,
            'review_notes' => $notes,
        ]);

        // Copiar descriptor al empleado e invalidar caché de descriptores
        $this->employee->update([
            'face_descriptor' => $this->face_descriptor,
        ]);
        Cache::forget('employees_face_descriptors');

        // Expirar otros enrollments pendientes del mismo empleado
        static::where('employee_id', $this->employee_id)
            ->where('id', '!=', $this->id)
            ->whereIn('status', ['pending_capture', 'pending_approval'])
            ->update(['status' => 'expired']);

        Log::info('Registro facial aprobado', [
            'enrollment_id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => "{$this->employee->first_name} {$this->employee->last_name}",
            'reviewed_by_id' => $reviewedById,
        ]);

        return [
            'success' => true,
            'message' => "Registro facial de {$this->employee->first_name} {$this->employee->last_name} aprobado. El empleado ya puede marcar asistencia.",
        ];
    }

    /**
     * Rechaza el registro facial.
     */
    public function reject(int $reviewedById, string $notes): array
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'reviewed_by_id' => $reviewedById,
            'review_notes' => $notes,
        ]);

        Log::info('Registro facial rechazado', [
            'enrollment_id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee_name' => "{$this->employee->first_name} {$this->employee->last_name}",
            'reviewed_by_id' => $reviewedById,
            'reason' => $notes,
        ]);

        return [
            'success' => true,
            'message' => "Registro facial de {$this->employee->first_name} {$this->employee->last_name} rechazado.",
        ];
    }

    // =========================================================================
    // FACTORY
    // =========================================================================

    /**
     * Crea un nuevo enrollment con token temporal para un empleado.
     */
    public static function createForEmployee(Employee $employee, int $generatedById, int $expiryHours): self
    {
        return static::create([
            'employee_id' => $employee->id,
            'token' => Str::random(64),
            'generated_by_id' => $generatedById,
            'expires_at' => now()->addHours($expiryHours),
        ]);
    }
}
