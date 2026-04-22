<?php

namespace App\Filament\Resources\WarningResource\Pages;

use App\Filament\Resources\WarningResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/** Página de visualización de una amonestación. */
class ViewWarning extends ViewRecord
{
    protected static string $resource = WarningResource::class;

    /**
     * Define las acciones del encabezado de la página de detalle.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn () => route('warnings.pdf', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()->icon('heroicon-o-pencil-square'),
        ];
    }
}
