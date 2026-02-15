<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Contract extends Model
{
    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'trial_days',
        'salary_type',
        'salary',
        'position_id',
        'department_id',
        'work_modality',
        'document_path',
        'status',
        'notes',
        'created_by_id',
    ];

    protected $casts = [
        'start_date'  => 'date',
        'end_date'    => 'date',
        'trial_days'  => 'integer',
        'salary'      => 'integer',
    ];

    // ───────────────────────────────────────────
    // Relaciones
    // ───────────────────────────────────────────

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // ───────────────────────────────────────────
    // Opciones estáticas
    // ───────────────────────────────────────────

    /**
     * Tipos de contrato según el Código Laboral Paraguayo (Ley 213/93)
     */
    public static function getTypeOptions(): array
    {
        return [
            'indefinido'       => 'Por Tiempo Indefinido',
            'plazo_fijo'       => 'Por Plazo Determinado',
            'obra_determinada' => 'Por Obra Determinada',
            'aprendizaje'      => 'De Aprendizaje',
            'pasantia'         => 'Pasantía',
        ];
    }

    public static function getTypeLabel(string $type): string
    {
        return self::getTypeOptions()[$type] ?? $type;
    }

    public static function getTypeColor(string $type): string
    {
        return match ($type) {
            'indefinido'       => 'success',
            'plazo_fijo'       => 'warning',
            'obra_determinada' => 'info',
            'aprendizaje'      => 'primary',
            'pasantia'         => 'gray',
            default            => 'gray',
        };
    }

    public static function getTypeIcon(string $type): string
    {
        return match ($type) {
            'indefinido'       => 'heroicon-o-check-badge',
            'plazo_fijo'       => 'heroicon-o-calendar',
            'obra_determinada' => 'heroicon-o-wrench-screwdriver',
            'aprendizaje'      => 'heroicon-o-academic-cap',
            'pasantia'         => 'heroicon-o-book-open',
            default            => 'heroicon-o-document-text',
        };
    }

    public static function getStatusOptions(): array
    {
        return [
            'active'     => 'Vigente',
            'expired'    => 'Vencido',
            'terminated' => 'Terminado',
            'renewed'    => 'Renovado',
        ];
    }

    public static function getStatusLabel(string $status): string
    {
        return self::getStatusOptions()[$status] ?? $status;
    }

    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'active'     => 'success',
            'expired'    => 'danger',
            'terminated' => 'gray',
            'renewed'    => 'info',
            default      => 'gray',
        };
    }

    public static function getStatusIcon(string $status): string
    {
        return match ($status) {
            'active'     => 'heroicon-o-check-circle',
            'expired'    => 'heroicon-o-exclamation-triangle',
            'terminated' => 'heroicon-o-x-circle',
            'renewed'    => 'heroicon-o-arrow-path',
            default      => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Tipos de remuneración según Art. 231 del Código Laboral Paraguayo
     */
    public static function getSalaryTypeOptions(): array
    {
        return [
            'mensual' => 'Mensualizado (Sueldo)',
            'jornal'  => 'Jornalero (Jornal Diario)',
        ];
    }

    public static function getSalaryTypeLabel(string $salaryType): string
    {
        return self::getSalaryTypeOptions()[$salaryType] ?? $salaryType;
    }

    public static function getSalaryTypeColor(string $salaryType): string
    {
        return match ($salaryType) {
            'mensual' => 'info',
            'jornal'  => 'warning',
            default   => 'gray',
        };
    }

    public static function getSalaryTypeIcon(string $salaryType): string
    {
        return match ($salaryType) {
            'mensual' => 'heroicon-o-banknotes',
            'jornal'  => 'heroicon-o-calendar-days',
            default   => 'heroicon-o-question-mark-circle',
        };
    }

    public static function getWorkModalityOptions(): array
    {
        return [
            'presencial' => 'Presencial',
            'remoto'     => 'Remoto',
            'hibrido'    => 'Híbrido',
        ];
    }

    public static function getWorkModalityLabel(string $modality): string
    {
        return self::getWorkModalityOptions()[$modality] ?? $modality;
    }

    public static function getWorkModalityColor(string $modality): string
    {
        return match ($modality) {
            'presencial' => 'success',
            'remoto'     => 'info',
            'hibrido'    => 'warning',
            default      => 'gray',
        };
    }

    public static function getWorkModalityIcon(string $modality): string
    {
        return match ($modality) {
            'presencial' => 'heroicon-o-building-office',
            'remoto'     => 'heroicon-o-home',
            'hibrido'    => 'heroicon-o-arrows-right-left',
            default      => 'heroicon-o-question-mark-circle',
        };
    }

    // ───────────────────────────────────────────
    // Lógica de negocio
    // ───────────────────────────────────────────

    /**
     * Verifica si el contrato requiere fecha de finalización
     */
    public static function requiresEndDate(string $type): bool
    {
        return in_array($type, ['plazo_fijo', 'obra_determinada', 'aprendizaje', 'pasantia']);
    }

    /**
     * Verifica si el contrato está por vencer dentro de los días indicados
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->end_date
            && $this->status === 'active'
            && $this->end_date->isFuture()
            && $this->end_date->diffInDays(now()) <= $days;
    }

    /**
     * Verifica si el contrato ya venció
     */
    public function isExpired(): bool
    {
        return $this->end_date
            && $this->status === 'active'
            && $this->end_date->isPast();
    }

    /**
     * Verifica si el empleado está en período de prueba (Art. 58 CLT)
     */
    public function isInTrialPeriod(): bool
    {
        if (!$this->trial_days || $this->status !== 'active') {
            return false;
        }

        $trialEnd = $this->start_date->copy()->addDays($this->trial_days);

        return now()->lte($trialEnd);
    }

    /**
     * Obtiene la fecha de fin del período de prueba
     */
    public function getTrialEndDateAttribute(): ?Carbon
    {
        if (!$this->trial_days) {
            return null;
        }

        return $this->start_date->copy()->addDays($this->trial_days);
    }

    /**
     * Obtiene la duración del contrato en texto legible
     */
    public function getDurationDescriptionAttribute(): string
    {
        if (!$this->end_date) {
            return 'Indefinido';
        }

        $diff = $this->start_date->diff($this->end_date);

        $parts = [];
        if ($diff->y > 0) {
            $parts[] = $diff->y . ' ' . ($diff->y === 1 ? 'año' : 'años');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m . ' ' . ($diff->m === 1 ? 'mes' : 'meses');
        }
        if ($diff->d > 0 && $diff->y === 0) {
            $parts[] = $diff->d . ' ' . ($diff->d === 1 ? 'día' : 'días');
        }

        return implode(', ', $parts) ?: '0 días';
    }

    /**
     * Obtiene los días restantes hasta el vencimiento
     */
    public function getRemainingDaysAttribute(): ?int
    {
        if (!$this->end_date || $this->status !== 'active') {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->end_date->startOfDay(), false);
    }

    /**
     * Descripción del estado de vencimiento
     */
    public function getExpirationDescriptionAttribute(): ?string
    {
        if (!$this->end_date) {
            return null;
        }

        $remaining = $this->remaining_days;

        if ($remaining === null) {
            return null;
        }

        if ($remaining < 0) {
            return 'Venció hace ' . abs($remaining) . ' días';
        }

        if ($remaining === 0) {
            return 'Vence hoy';
        }

        return 'Vence en ' . $remaining . ' días';
    }

    /**
     * Termina el contrato
     */
    public function terminate(): self
    {
        $this->update(['status' => 'terminated']);

        return $this;
    }

    /**
     * Renueva el contrato creando uno nuevo.
     * Art. 53 CLT: Si un contrato a plazo fijo se renueva más de una vez,
     * se convierte automáticamente en indefinido.
     */
    public function renew(array $newContractData): Contract
    {
        // Marcar este contrato como renovado
        $this->update(['status' => 'renewed']);

        // Art. 53 CLT: Verificar si ya fue renovado antes (segunda renovación = indefinido)
        $previousRenewals = static::where('employee_id', $this->employee_id)
            ->where('status', 'renewed')
            ->where('type', 'plazo_fijo')
            ->count();

        // Si es la segunda renovación de un plazo fijo, forzar tipo indefinido
        $forceIndefinido = $this->type === 'plazo_fijo' && $previousRenewals >= 2;

        $data = array_merge([
            'employee_id'   => $this->employee_id,
            'type'          => $forceIndefinido ? 'indefinido' : $this->type,
            'salary_type'   => $this->salary_type,
            'salary'        => $this->salary,
            'position_id'   => $this->position_id,
            'department_id' => $this->department_id,
            'work_modality' => $this->work_modality,
            'status'        => 'active',
        ], $newContractData);

        // Si se forzó indefinido, quitar fecha de fin
        if ($forceIndefinido) {
            $data['end_date'] = null;
        }

        return static::create($data);
    }

    /**
     * Verifica si al renovar este contrato se convertiría en indefinido (Art. 53 CLT)
     */
    public function wouldBecomeIndefiniteOnRenewal(): bool
    {
        if ($this->type !== 'plazo_fijo') {
            return false;
        }

        $previousRenewals = static::where('employee_id', $this->employee_id)
            ->where('status', 'renewed')
            ->where('type', 'plazo_fijo')
            ->count();

        // El contrato actual también cuenta como renovación al renovarse
        return $previousRenewals >= 1;
    }

    /**
     * Sincroniza el salario, cargo y tipo de empleo del contrato al empleado (Fase 1)
     */
    public function syncToEmployee(): void
    {
        $employmentType = match ($this->salary_type) {
            'jornal'  => 'day_laborer',
            default   => 'full_time',
        };

        $updateData = [
            'position_id'     => $this->position_id,
            'employment_type' => $employmentType,
        ];

        if ($this->salary_type === 'mensual') {
            $updateData['base_salary'] = $this->salary;
        } else {
            // Jornalero: base_salary se deja null, se usa daily_rate
            $updateData['base_salary'] = null;
            $updateData['daily_rate'] = $this->salary;
        }

        $this->employee->update($updateData);
    }

    // ───────────────────────────────────────────
    // Scopes
    // ───────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeExpiringSoon(Builder $query, int $days = 30): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '>', now())
            ->where('end_date', '<=', now()->addDays($days));
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->whereNotNull('end_date')
            ->where('end_date', '<', now());
    }

    // ───────────────────────────────────────────
    // Formateadores
    // ───────────────────────────────────────────

    public static function formatCurrency(float|int|null $amount): string
    {
        if ($amount === null) {
            return 'Gs. 0';
        }

        return 'Gs. ' . number_format((int) $amount, 0, ',', '.');
    }

    public function getFormattedSalaryAttribute(): string
    {
        $formatted = self::formatCurrency($this->salary);

        return $this->salary_type === 'jornal'
            ? $formatted . '/día'
            : $formatted . '/mes';
    }

    public function getFormattedStartDateAttribute(): string
    {
        return $this->start_date->format('d/m/Y');
    }

    public function getFormattedEndDateAttribute(): ?string
    {
        return $this->end_date?->format('d/m/Y');
    }

    /**
     * Obtiene el conteo de contratos por estado para badges
     */
    public static function getCounts(): array
    {
        return [
            'active'   => static::where('status', 'active')->count(),
            'expiring' => static::expiringSoon(
                app(\App\Settings\GeneralSettings::class)->contract_alert_days
            )->count(),
            'expired'  => static::expired()->count(),
        ];
    }

    /**
     *  Calcula los días restantes del período de prueba (Art. 58 CLT)
     *
     * @return integer|null
     */
    public function getTrialDaysLeftAttribute(): ?int
    {
        if (!$this->trial_days || $this->status !== 'active') {
            return null;
        }

        $trialEnd = $this->start_date->copy()->addDays($this->trial_days);

        if (now()->gt($trialEnd)) {
            return 0;
        }

        return (int) now()->startOfDay()->diffInDays($trialEnd->startOfDay(), false);
    }
}
