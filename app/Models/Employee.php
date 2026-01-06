<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'photo',
        'first_name',
        'last_name',
        'ci',
        'birth_date',
        'phone',
        'email',
        'hire_date',
        'payroll_type',
        'employment_type',
        'base_salary',
        'daily_rate',
        'payment_method',
        'position_id',
        'branch_id',
        'schedule_id',
        'status',
        'face_descriptor',
    ];

    protected $casts = [
        'birth_date'   => 'date',
        'hire_date'     => 'date',
        'base_salary'   => 'decimal:2',
        'daily_rate'   => 'decimal:2',
        'face_descriptor' => 'array',
    ];

    /**
     * Relación con el modelo Position, un empleado pertenece a una posición
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Relación con el modelo Branch, un empleado pertenece a una sucursal
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Deducciones aplicadas al empleado
     */
    public function deductions(): BelongsToMany
    {
        return $this->belongsToMany(Deduction::class, 'employee_deductions')
            ->using(EmployeeDeduction::class)
            ->withPivot('start_date', 'end_date', 'custom_amount', 'notes')
            ->withTimestamps();
    }

    /**
     * Percepciones aplicadas al empleado
     */
    public function perceptions(): BelongsToMany
    {
        return $this->belongsToMany(Perception::class, 'employee_perceptions')
            ->using(EmployeePerception::class)
            ->withPivot('start_date', 'end_date', 'custom_amount', 'notes')
            ->withTimestamps();
    }

    /**
     * Relación con el modelo EmployeeDeduction, un empleado puede tener muchas deducciones
     */
    public function employeeDeductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    /**
     * Relación con el modelo EmployeePerception, un empleado puede tener muchas percepciones
     */
    public function employeePerceptions(): HasMany
    {
        return $this->hasMany(EmployeePerception::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    // Relación con el modelo Attendance, un empleado puede tener muchas asistencias
    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }

    // Obtener todos los eventos de asistencia a través de los días
    public function attendanceEvents(): HasManyThrough
    {
        return $this->hasManyThrough(
            AttendanceEvent::class,
            AttendanceDay::class,
            'employee_id',        // FK en attendance_days
            'attendance_day_id',   // FK en attendance_events
            'id',                  // PK en employees
            'id'                   // PK en attendance_days
        );
    }

    /**
     * Relación con el modelo ScheduleType, un empleado puede tener un horario asignado
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Relación con el modelo Document, un empleado puede tener muchos documentos
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Relación con el modelo Vacationm, un empleado puede tener muchas vacaciones
     */
    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    /**
     * Relación con el modelo EmployeeLeave, un empleado puede tener muchos permisos
     */
    public function leaves(): HasMany
    {
        return $this->hasMany(EmployeeLeave::class);
    }

    public function getTodayScheduledCheckInAttribute()
    {
        $today = Carbon::now()->dayOfWeekIso;

        $scheduleDay = $this->schedule?->days->firstWhere('day_of_week', $today);

        if ($scheduleDay) {
            return $scheduleDay->start_time;
        }

        return null;
    }

    public function getTodayScheduledCheckOutAttribute()
    {
        $today = Carbon::now()->dayOfWeekIso;

        $scheduleDay = $this->schedule?->days->firstWhere('day_of_week', $today);

        if ($scheduleDay) {
            return $scheduleDay->end_time;
        }

        return null;
    }

    public function getTodayExpectedBreakMinutesAttribute()
    {
        $today = Carbon::now()->dayOfWeekIso;

        $scheduleDay = $this->schedule?->days->firstWhere('day_of_week', $today);

        if ($scheduleDay) {
            return $scheduleDay->total_break_minutes;
        }

        return null;
    }

    public function scopeWithPayrollTypeAndSalary($query, string $type)
    {
        return $query->where('payroll_type', $type)
            ->whereNotNull('base_salary');
    }

    /**
     * Scope para filtrar empleados activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para filtrar empleados inactivos
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Scope para filtrar empleados suspendidos
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    /**
     * Scope para filtrar empleados con rostro registrado
     */
    public function scopeWithFace($query)
    {
        return $query->whereNotNull('face_descriptor');
    }

    /**
     * Scope para filtrar empleados sin rostro registrado
     */
    public function scopeWithoutFace($query)
    {
        return $query->whereNull('face_descriptor');
    }

    /**
     * Obtiene los conteos de empleados por estado y rostro de forma optimizada
     */
    public static function getTabCounts(): array
    {
        $statusCounts = static::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $faceCounts = static::selectRaw('
            SUM(CASE WHEN face_descriptor IS NOT NULL THEN 1 ELSE 0 END) as with_face,
            SUM(CASE WHEN face_descriptor IS NULL THEN 1 ELSE 0 END) as without_face
        ')
            ->first();

        return [
            'all' => static::count(),
            'active' => $statusCounts['active'] ?? 0,
            'inactive' => $statusCounts['inactive'] ?? 0,
            'suspended' => $statusCounts['suspended'] ?? 0,
            'with_face' => $faceCounts->with_face ?? 0,
            'without_face' => $faceCounts->without_face ?? 0,
        ];
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Obtiene la URL de la foto del empleado o la imagen por defecto
     */
    public function getPhotoUrlAttribute(): string
    {
        return $this->photo ?: url('/images/default-avatar.png');
    }

    /**
     * Obtiene las opciones de tipo de empleo para filtros y selects
     */
    public static function getEmploymentTypeOptions(): array
    {
        return [
            'full_time' => 'Tiempo Completo',
            'day_laborer' => 'Jornalero',
        ];
    }

    /**
     * Obtiene las opciones de tipo de nómina para filtros y selects
     */
    public static function getPayrollTypeOptions(): array
    {
        return [
            'monthly' => 'Mensual',
            'biweekly' => 'Quincenal',
            'weekly' => 'Semanal',
        ];
    }

    /**
     * Obtiene las opciones de método de pago para filtros y selects
     */
    public static function getPaymentMethodOptions(): array
    {
        return [
            'debit' => 'Tarjeta de Débito',
            'cash' => 'Efectivo',
            'check' => 'Cheque',
        ];
    }

    /**
     * Obtiene las opciones de estado para filtros y selects
     */
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'suspended' => 'Suspendido',
        ];
    }

    /**
     * Obtiene las opciones de meses para filtros
     */
    public static function getMonthOptions(): array
    {
        return [
            1  => 'Enero',
            2  => 'Febrero',
            3  => 'Marzo',
            4  => 'Abril',
            5  => 'Mayo',
            6  => 'Junio',
            7  => 'Julio',
            8  => 'Agosto',
            9  => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];
    }

    /**
     * Obtiene el color del badge para el tipo de empleo
     */
    public function getEmploymentTypeColorAttribute(): string
    {
        return match ($this->employment_type) {
            'full_time' => 'success',
            'day_laborer' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el tipo de empleo
     */
    public function getEmploymentTypeIconAttribute(): string
    {
        return match ($this->employment_type) {
            'full_time' => 'heroicon-o-clock',
            'day_laborer' => 'heroicon-o-calendar-days',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del tipo de empleo
     */
    public function getEmploymentTypeLabelAttribute(): string
    {
        return self::getEmploymentTypeOptions()[$this->employment_type] ?? $this->employment_type;
    }

    /**
     * Obtiene el color del badge para el tipo de nómina
     */
    public function getPayrollTypeColorAttribute(): string
    {
        return match ($this->payroll_type) {
            'monthly' => 'info',
            'biweekly' => 'success',
            'weekly' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el tipo de nómina
     */
    public function getPayrollTypeIconAttribute(): string
    {
        return match ($this->payroll_type) {
            'monthly' => 'heroicon-o-calendar',
            'biweekly' => 'heroicon-o-calendar-days',
            'weekly' => 'heroicon-o-clock',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del tipo de nómina
     */
    public function getPayrollTypeLabelAttribute(): string
    {
        return self::getPayrollTypeOptions()[$this->payroll_type] ?? $this->payroll_type;
    }

    /**
     * Obtiene el color del badge para el estado del empleado
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'inactive' => 'danger',
            'suspended' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el estado del empleado
     */
    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'active' => 'heroicon-o-check-circle',
            'inactive' => 'heroicon-o-x-circle',
            'suspended' => 'heroicon-o-pause-circle',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del estado del empleado
     */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusOptions()[$this->status] ?? $this->status;
    }

    /**
     * Obtiene la URL de contacto (WhatsApp o Email)
     */
    public function getContactUrlAttribute(): ?string
    {
        if ($this->phone) {
            return 'https://api.whatsapp.com/send?phone=595' . $this->phone;
        }

        if ($this->email) {
            return 'mailto:' . $this->email;
        }

        return null;
    }

    /**
     * Obtiene el texto de contacto formateado
     */
    public function getContactTextAttribute(): string
    {
        return $this->phone ?: 'Sin datos';
    }

    /**
     * Verifica si el empleado tiene rostro registrado
     */
    public function getHasFaceAttribute(): bool
    {
        return filled($this->face_descriptor);
    }

    /**
     * Obtiene el tooltip para el rostro
     */
    public function getFaceTooltipAttribute(): string
    {
        return $this->has_face ? 'Rostro registrado' : 'Sin rostro registrado';
    }

    /**
     * Obtiene la descripción de antigüedad
     */
    public function getAntiquityDescriptionAttribute(): string
    {
        return $this->hire_date->diffForHumans(null, true) . ' en la empresa';
    }

    /**
     * Obtiene la descripción de la edad
     */
    public function getAgeDescriptionAttribute(): string
    {
        return $this->birth_date ? $this->birth_date->age . ' años' : '';
    }

    /**
     * Obtiene el color del badge para el método de pago
     */
    public function getPaymentMethodColorAttribute(): string
    {
        return match ($this->payment_method) {
            'debit' => 'success',
            'cash' => 'warning',
            'check' => 'info',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono para el método de pago
     */
    public function getPaymentMethodIconAttribute(): string
    {
        return match ($this->payment_method) {
            'debit' => 'heroicon-o-credit-card',
            'cash' => 'heroicon-o-banknotes',
            'check' => 'heroicon-o-document-text',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    /**
     * Obtiene la etiqueta formateada del método de pago
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return self::getPaymentMethodOptions()[$this->payment_method] ?? $this->payment_method;
    }

    /**
     * Obtiene la descripción formateada de created_at
     */
    public function getCreatedAtDescriptionAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha formateada tipo SINCE de created_at
     */
    public function getCreatedAtSinceAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Obtiene la descripción formateada de updated_at
     */
    public function getUpdatedAtDescriptionAttribute(): string
    {
        return $this->updated_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha formateada tipo SINCE de updated_at
     */
    public function getUpdatedAtSinceAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Sanitiza y normaliza los datos del formulario de empleado
     */
    public static function sanitizeFormData(array $data, bool $isCreating = false): array
    {
        // Convertir nombres y apellidos a mayúsculas
        if (isset($data['first_name'])) {
            $data['first_name'] = mb_strtoupper($data['first_name'], 'UTF-8');
        }

        if (isset($data['last_name'])) {
            $data['last_name'] = mb_strtoupper($data['last_name'], 'UTF-8');
        }

        // Convertir email a minúsculas y limpiar espacios
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Limpiar CI: eliminar ceros a la izquierda y espacios
        if (isset($data['ci'])) {
            $data['ci'] = ltrim(str_replace(' ', '', $data['ci']), '0') ?: '0';
        }

        // Limpiar teléfono: eliminar espacios, guiones y ceros a la izquierda
        if (isset($data['phone'])) {
            $cleaned = str_replace([' ', '-', '+595'], '', $data['phone']);
            $data['phone'] = ltrim($cleaned, '0') ?: null;
        }

        // Asegurar que solo se guarde el campo correcto según el tipo de empleo
        if (isset($data['employment_type'])) {
            if ($data['employment_type'] === 'full_time') {
                // Si es tiempo completo, asegurar que daily_rate sea null
                $data['daily_rate'] = null;

                // Limpiar y validar base_salary
                if (isset($data['base_salary'])) {
                    $data['base_salary'] = (float) $data['base_salary'] ?: null;
                }
            } else {
                // Si es jornalero, asegurar que base_salary sea null
                $data['base_salary'] = null;

                // Limpiar y validar daily_rate
                if (isset($data['daily_rate'])) {
                    $data['daily_rate'] = (float) $data['daily_rate'] ?: null;
                }
            }
        }

        // Si es creación, asegurar que face_descriptor sea null
        if ($isCreating) {
            $data['face_descriptor'] = null;
        }

        return $data;
    }

    /**
     * Asigna todas las deducciones obligatorias activas al empleado
     *
     * @return int Cantidad de deducciones asignadas
     * @throws \Exception Si hay un error al asignar las deducciones
     */
    public function assignMandatoryDeductions(): int
    {
        // Obtener todas las deducciones obligatorias y activas
        $mandatoryDeductions = Deduction::where('is_mandatory', true)
            ->where('is_active', true)
            ->get();

        if ($mandatoryDeductions->isEmpty()) {
            throw new \Exception('No hay deducciones obligatorias activas disponibles.');
        }

        // Obtener los IDs de las deducciones ya asignadas al empleado
        $existingDeductionIds = $this->employeeDeductions()
            ->whereNull('end_date')
            ->pluck('deduction_id')
            ->toArray();

        $assignedCount = 0;

        // Asignar solo las deducciones que no están ya asignadas
        foreach ($mandatoryDeductions as $deduction) {
            if (!in_array($deduction->id, $existingDeductionIds)) {
                $this->employeeDeductions()->create([
                    'deduction_id' => $deduction->id,
                    'start_date' => now(),
                    'end_date' => null,
                    'custom_amount' => null,
                    'notes' => 'Deducción obligatoria asignada automáticamente',
                ]);
                $assignedCount++;
            }
        }

        if ($assignedCount === 0) {
            throw new \Exception('Todas las deducciones obligatorias ya están asignadas al empleado.');
        }

        return $assignedCount;
    }
}
