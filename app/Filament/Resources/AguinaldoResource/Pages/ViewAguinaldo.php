<?php

namespace App\Filament\Resources\AguinaldoResource\Pages;

use App\Filament\Resources\AguinaldoResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAguinaldo extends ViewRecord
{
    protected static string $resource = AguinaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn() => route('aguinaldos.download', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
