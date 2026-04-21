<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Filament\Resources\AdvanceResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAdvance extends ViewRecord
{
    protected static string $resource = AdvanceResource::class;

    /**
     * Define las acciones del encabezado de la página de detalle.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn() => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Aprobar Adelanto')
                ->modalDescription(fn() => 'Se aprobará el adelanto de ' . number_format((float) $this->record->amount, 0, ',', '.') . ' Gs. para ' . $this->record->employee->full_name . '. Se descontará automáticamente en la próxima liquidación de nómina.')
                ->modalSubmitActionLabel('Sí, aprobar')
                ->action(function () {
                    $result = $this->record->approve(Auth::id());

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Adelanto Aprobado')
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'approved_at', 'approved_by_id']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('reject')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->visible(fn() => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Rechazar Adelanto')
                ->modalDescription(fn() => 'Se rechazará el adelanto de ' . number_format((float) $this->record->amount, 0, ',', '.') . ' Gs. para ' . $this->record->employee->full_name . '. El adelanto quedará en estado Rechazado.')
                ->modalSubmitActionLabel('Sí, rechazar')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo del rechazo')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->reject($data['reason'] ?? null);

                    if ($result['success']) {
                        Notification::make()
                            ->warning()
                            ->title('Adelanto Rechazado')
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'notes']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->visible(fn() => $this->record->isPending() || $this->record->isApproved())
                ->requiresConfirmation()
                ->modalHeading('Cancelar Adelanto')
                ->modalDescription(fn() => '¿Está seguro de que desea cancelar el adelanto de ' . number_format((float) $this->record->amount, 0, ',', '.') . ' Gs.?')
                ->modalSubmitActionLabel('Sí, cancelar')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de cancelación')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->cancel($data['reason'] ?? null);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Adelanto Cancelado')
                            ->send();

                        $this->refreshFormData(['status', 'notes']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('export_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn() => $this->record->isApproved() || $this->record->isPaid())
                ->url(fn() => route('advances.pdf', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn() => $this->record->isPending()),
        ];
    }
}
