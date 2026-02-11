<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn() => route('payrolls.download', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->visible(fn() => $this->record->period?->status !== 'closed'),

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
}
