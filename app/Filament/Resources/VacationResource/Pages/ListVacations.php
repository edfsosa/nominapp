<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Pages\VacationBalances;
use App\Filament\Resources\VacationResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVacations extends ListRecords
{
    protected static string $resource = VacationResource::class;

    /**
     * @return array<int, Action|CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewBalances')
                ->label('Ver Balances')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->url(VacationBalances::getUrl()),

            CreateAction::make()
                ->label('Nueva Solicitud')
                ->icon('heroicon-o-plus'),
        ];
    }
}
