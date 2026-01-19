<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id',
        'installment_number',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'employee_deduction_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    /**
     * Relación con el préstamo
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Relación con la deducción del empleado
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
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
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
            'paid' => 'success',
            'cancelled' => 'gray',
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
            'paid' => 'heroicon-o-check-circle',
            'cancelled' => 'heroicon-o-x-circle',
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
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
        ];
    }

    /**
     * Verifica si la cuota está pendiente
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Verifica si la cuota está pagada
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Verifica si la cuota fue cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Verifica si la cuota está vencida
     */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_date->isPast();
    }

    /**
     * Obtiene la descripción de la cuota
     */
    public function getDescriptionAttribute(): string
    {
        return "Cuota {$this->installment_number}/{$this->loan->installments_count}";
    }

    /**
     * Obtiene la descripción completa de la cuota
     */
    public function getFullDescriptionAttribute(): string
    {
        $loanType = Loan::getTypeLabel($this->loan->type);
        return "{$loanType} - Cuota {$this->installment_number}/{$this->loan->installments_count}";
    }

    /**
     * Marca la cuota como pagada y crea la deducción
     *
     * @return array Resultado con 'success' y 'message'
     */
    public function markAsPaid(): array
    {
        if (!$this->isPending()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden marcar como pagadas las cuotas pendientes.',
            ];
        }

        // Buscar o crear la deducción por préstamos
        $deduction = Deduction::firstOrCreate(
            ['code' => 'PREST'],
            [
                'name' => 'Cuota de Préstamo',
                'description' => 'Deducción por cuota de préstamo o adelanto',
                'calculation' => 'fixed',
                'is_mandatory' => true,
                'is_active' => true,
                'affects_ips' => false,
                'affects_irp' => false,
            ]
        );

        $loan = $this->loan;
        $employee = $loan->employee;

        // Crear la deducción del empleado
        $employeeDeduction = EmployeeDeduction::create([
            'employee_id' => $employee->id,
            'deduction_id' => $deduction->id,
            'start_date' => $this->due_date,
            'end_date' => $this->due_date,
            'custom_amount' => $this->amount,
            'notes' => $this->full_description . " - Vencimiento: {$this->due_date->format('d/m/Y')}",
        ]);

        // Actualizar la cuota
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'employee_deduction_id' => $employeeDeduction->id,
        ]);

        // Verificar si el préstamo está completamente pagado
        $loan->checkIfPaid();

        return [
            'success' => true,
            'message' => "Cuota {$this->installment_number} marcada como pagada.",
        ];
    }

    /**
     * Revierte el pago de la cuota
     *
     * @return array Resultado con 'success' y 'message'
     */
    public function revertPayment(): array
    {
        if (!$this->isPaid()) {
            return [
                'success' => false,
                'message' => 'Solo se pueden revertir cuotas pagadas.',
            ];
        }

        // Eliminar la deducción si existe
        if ($this->employee_deduction_id) {
            $deduction = EmployeeDeduction::find($this->employee_deduction_id);
            if ($deduction) {
                $deduction->delete();
            }
        }

        // Actualizar la cuota
        $this->update([
            'status' => 'pending',
            'paid_at' => null,
            'employee_deduction_id' => null,
        ]);

        // Actualizar estado del préstamo si estaba pagado
        $loan = $this->loan;
        if ($loan->isPaid()) {
            $loan->update(['status' => 'active']);
        }

        return [
            'success' => true,
            'message' => "Pago de cuota {$this->installment_number} revertido.",
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
     * Scope para obtener cuotas pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope para obtener cuotas pagadas
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Scope para obtener cuotas vencidas
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', now());
    }

    /**
     * Scope para obtener cuotas del mes actual
     */
    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('due_date', now()->month)
            ->whereYear('due_date', now()->year);
    }

    /**
     * Scope para filtrar por rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('due_date', [$startDate, $endDate]);
    }
}
