<?php

namespace App\Filament\Resources\AbsentResource\Pages;

use App\Models\Absent;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\AbsentResource;
use Illuminate\Database\Eloquent\Builder;
use Filament\Resources\Pages\ListRecords\Tab;

class ListAbsents extends ListRecords
{
    protected static string $resource = AbsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AbsentResource::getExcelExportAction(),

            CreateAction::make()
                ->label('Nueva Ausencia')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(Absent::count()),

            'pending' => Tab::make('Pendientes')
                ->badge(Absent::where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending')),

            'justified' => Tab::make('Justificadas')
                ->badge(Absent::where('status', 'justified')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'justified')),

            'unjustified' => Tab::make('Injustificadas')
                ->badge(Absent::where('status', 'unjustified')->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'unjustified')),
        ];
    }
}
