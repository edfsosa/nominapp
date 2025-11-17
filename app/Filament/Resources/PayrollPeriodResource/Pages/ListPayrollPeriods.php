<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos'),
            'monthly' => Tab::make('Mensuales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'monthly')),
            'biweekly' => Tab::make('Quincenales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'biweekly')),
            'weekly' => Tab::make('Semanales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'weekly')),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'monthly';
    }
}
