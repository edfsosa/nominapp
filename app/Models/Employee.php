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

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
