<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use App\Models\Absence;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\AbsenceResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords\Tab;

class ListAbsences extends ListRecords
{
    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AbsenceResource::getExcelExportAction(),

            CreateAction::make()
                ->label('Nueva Ausencia')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(Absence::count()),

            'pending' => Tab::make('Pendientes')
                ->badge(Absence::where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending')),

            'justified' => Tab::make('Justificadas')
                ->badge(Absence::where('status', 'justified')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'justified')),

            'unjustified' => Tab::make('Injustificadas')
                ->badge(Absence::where('status', 'unjustified')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'unjustified')),
        ];
    }
}
