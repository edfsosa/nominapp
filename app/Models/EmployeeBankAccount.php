<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta bancaria de un empleado para acreditación de salarios.
 *
 * Un empleado puede tener múltiples cuentas; solo una activa puede ser principal.
 * La cuenta principal se preselecciona al generar nóminas con payment_method = transferencia.
 */
class EmployeeBankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'bank',
        'account_number',
        'account_type',
        'holder_name',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    // =========================================================================
    // CATÁLOGOS
    // =========================================================================

    /** Bancos del Paraguay. */
    public const BANKS = [
        'banco_continental' => 'Banco Continental',
        'banco_itau' => 'Banco Itaú Paraguay',
        'banco_regional' => 'Banco Regional',
        'vision_banco' => 'Visión Banco',
        'banco_gnb' => 'Banco GNB Paraguay',
        'interfisa_banco' => 'Interfisa Banco',
        'banco_basa' => 'Banco BASA',
        'banco_familiar' => 'Banco Familiar',
        'sudameris_bank' => 'Sudameris Bank',
        'atlas_banco' => 'Atlas Banco',
        'banco_rio' => 'Banco Río',
        'ueno_bank' => 'Ueno Bank',
        'banco_nacional_fomento' => 'Banco Nacional de Fomento (BNF)',
        'afc_paraguay' => 'AFC Paraguay',
        'financiera_el_comercio' => 'Financiera El Comercio',
        'credifácil' => 'Credifácil',
        'otro' => 'Otro',
    ];

    /** Tipos de cuenta. */
    public const ACCOUNT_TYPES = [
        'savings' => 'Caja de Ahorro',
        'checking' => 'Cuenta Corriente',
        'salary' => 'Cuenta Sueldo',
    ];

    // =========================================================================
    // RELACIONES
    // =========================================================================

    /** Empleado al que pertenece la cuenta. */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Nóminas en las que se usó esta cuenta. */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'bank_account_id');
    }

    // =========================================================================
    // HELPERS ESTÁTICOS — CATÁLOGOS
    // =========================================================================

    /**
     * Opciones de bancos para selects Filament.
     *
     * @return array<string, string>
     */
    public static function getBankOptions(): array
    {
        return self::BANKS;
    }

    /**
     * Opciones de tipos de cuenta para selects Filament.
     *
     * @return array<string, string>
     */
    public static function getAccountTypeOptions(): array
    {
        return self::ACCOUNT_TYPES;
    }

    /**
     * Label legible de un banco.
     */
    public static function getBankLabel(string $bank): string
    {
        return self::BANKS[$bank] ?? $bank;
    }

    /**
     * Label legible de un tipo de cuenta.
     */
    public static function getAccountTypeLabel(string $type): string
    {
        return self::ACCOUNT_TYPES[$type] ?? $type;
    }

    /**
     * Color semántico Filament para el estado.
     */
    public static function getStatusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Label legible del estado.
     */
    public static function getStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Activa',
            'inactive' => 'Inactiva',
            default => 'Desconocido',
        };
    }

    /**
     * Opciones de estado para selects.
     *
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        return [
            'active' => 'Activa',
            'inactive' => 'Inactiva',
        ];
    }

    // =========================================================================
    // VERIFICADORES DE ESTADO
    // =========================================================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // =========================================================================
    // ATRIBUTOS COMPUTADOS
    // =========================================================================

    /** Label del banco actual. */
    public function getBankLabelAttribute(): string
    {
        return self::getBankLabel($this->bank);
    }

    /** Label del tipo de cuenta actual. */
    public function getAccountTypeLabelAttribute(): string
    {
        return self::getAccountTypeLabel($this->account_type);
    }

    /** Label del estado actual. */
    public function getStatusLabelAttribute(): string
    {
        return self::getStatusLabel($this->status);
    }

    // =========================================================================
    // ACCIONES
    // =========================================================================

    /**
     * Marca esta cuenta como principal y desmarca las demás del mismo empleado.
     * Solo se puede marcar como principal si la cuenta está activa.
     *
     * @return array{success: bool, message: string}
     */
    public function markAsPrimary(): array
    {
        if (! $this->isActive()) {
            return [
                'success' => false,
                'message' => 'Solo se puede marcar como principal una cuenta activa.',
            ];
        }

        // Desmarcar todas las demás cuentas del empleado
        static::where('employee_id', $this->employee_id)
            ->where('id', '!=', $this->id)
            ->update(['is_primary' => false]);

        $this->update(['is_primary' => true]);

        return [
            'success' => true,
            'message' => 'Cuenta marcada como principal.',
        ];
    }

    /**
     * Desactiva la cuenta bancaria.
     * No se puede desactivar si es la única cuenta principal y hay otras activas.
     *
     * @return array{success: bool, message: string}
     */
    public function deactivate(): array
    {
        if ($this->isInactive()) {
            return ['success' => false, 'message' => 'La cuenta ya está inactiva.'];
        }

        if ($this->is_primary) {
            $otherActive = static::where('employee_id', $this->employee_id)
                ->where('id', '!=', $this->id)
                ->where('status', 'active')
                ->exists();

            if ($otherActive) {
                return [
                    'success' => false,
                    'message' => 'No se puede desactivar la cuenta principal. Asigná otra cuenta como principal primero.',
                ];
            }

            // Es la única activa, se desactiva y se quita el flag principal
            $this->update(['status' => 'inactive', 'is_primary' => false]);
        } else {
            $this->update(['status' => 'inactive']);
        }

        return ['success' => true, 'message' => 'Cuenta desactivada.'];
    }

    /**
     * Reactiva la cuenta bancaria.
     *
     * @return array{success: bool, message: string}
     */
    public function reactivate(): array
    {
        if ($this->isActive()) {
            return ['success' => false, 'message' => 'La cuenta ya está activa.'];
        }

        $this->update(['status' => 'active']);

        return ['success' => true, 'message' => 'Cuenta reactivada.'];
    }
}
