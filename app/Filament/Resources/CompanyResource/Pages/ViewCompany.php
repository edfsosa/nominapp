<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Filament\Resources\CompanyResource;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewCompany extends ViewRecord
{
    protected static string $resource = CompanyResource::class;

    /**
     * Define las acciones que estarán disponibles en la vista de detalles de la empresa.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
                
            Action::make('orgChart')
                ->label('Organigrama')
                ->icon('heroicon-o-rectangle-group')
                ->color('info')
                ->url(fn() => route('org-chart.show', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
