<?php

namespace App\Services;

use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

class ContractService
{
    /**
     * Renueva un contrato creando uno nuevo.
     * Aplica Art. 53 CLT: segunda renovación de plazo fijo = contrato indefinido.
     * Asigna el usuario autenticado como creador del nuevo contrato.
     */
    public static function renew(Contract $contract, array $data): Contract
    {
        $data['created_by_id'] = Auth::id();

        return $contract->renew($data);
    }

    /**
     * Termina un contrato registrando el motivo en las notas.
     */
    public static function terminate(Contract $contract, ?string $reason): void
    {
        $contract->update([
            'status' => 'terminated',
            'notes'  => $contract->notes
                ? $contract->notes . "\n\nTerminación: " . ($reason ?? 'Sin motivo especificado')
                : "Terminación: " . ($reason ?? 'Sin motivo especificado'),
        ]);
    }
}
