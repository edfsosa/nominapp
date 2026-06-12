<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Notificación de alerta por contrato próximo a vencer o ya vencido. */
class ContractAlertNotification extends Notification
{
    use Queueable;

    /** @param  'expiring'|'expired'  $alertType */
    public function __construct(
        public readonly Contract $contract,
        public readonly string $alertType,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $employee = $this->contract->employee;
        $employeeName = $employee?->full_name ?? 'Empleado desconocido';

        if ($this->alertType === 'expired') {
            $message = "El contrato de {$employeeName} venció el {$this->contract->end_date->format('d/m/Y')}.";
            $icon = 'heroicon-o-x-circle';
            $color = 'danger';
        } else {
            $days = $this->contract->remaining_days;
            $message = "El contrato de {$employeeName} vence en {$days} ".($days === 1 ? 'día' : 'días')
                ." ({$this->contract->end_date->format('d/m/Y')}).";
            $icon = 'heroicon-o-clock';
            $color = 'warning';
        }

        return [
            'title'        => $this->alertType === 'expired' ? 'Contrato vencido' : 'Contrato por vencer',
            'body'         => $message,
            'icon'         => $icon,
            'color'        => $color,
            'actions'      => [
                [
                    'label' => 'Ver contrato',
                    'url'   => "/admin/contratos/{$this->contract->id}",
                ],
            ],
            // Datos adicionales para lógica de deduplicación
            'contract_id'  => $this->contract->id,
            'alert_type'   => $this->alertType,
        ];
    }
}
