<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayroll extends EditRecord
{
    protected static string $resource = PayrollResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Verificar si el período está cerrado
        if ($this->record->period?->status === 'closed') {
            Notification::make()
                ->warning()
                ->title('Período cerrado')
                ->body('Este recibo pertenece a un período cerrado y no puede ser modificado.')
                ->persistent()
                ->send();

            redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn() => route('payrolls.download', $this->record))
                ->openUrlInNewTab(),

            DeleteAction::make()
                ->visible(fn() => $this->record->period?->status === 'draft')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Recibo eliminado')
                        ->body('El recibo ha sido eliminado correctamente.')
                ),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Recibo actualizado')
            ->body("El recibo de {$this->record->employee->full_name} ha sido actualizado correctamente.");
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Recalcular gross_salary
        $data['gross_salary'] = $data['base_salary'] + ($data['total_perceptions'] ?? 0);

        // Recalcular net_salary
        $data['net_salary'] = $data['gross_salary'] - ($data['total_deductions'] ?? 0);

        return $data;
    }
}
