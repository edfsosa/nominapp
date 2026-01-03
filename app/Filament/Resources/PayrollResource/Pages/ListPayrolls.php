<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Recibo')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $currentPeriod = PayrollPeriod::where('status', 'processing')
            ->orWhere('status', 'draft')
            ->latest('start_date')
            ->first();

        $tabs = [
            'all' => Tab::make('Todos')
                ->badge(Payroll::count()),
        ];

        if ($currentPeriod) {
            $tabs['current_period'] = Tab::make($currentPeriod->name)
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('payroll_period_id', $currentPeriod->id)
                )
                ->badge(Payroll::where('payroll_period_id', $currentPeriod->id)->count())
                ->badgeColor('success');
        }

        // Últimos 3 períodos
        $recentPeriods = PayrollPeriod::latest('start_date')
            ->take(3)
            ->get();

        foreach ($recentPeriods as $period) {
            if ($currentPeriod && $period->id === $currentPeriod->id) {
                continue; // Ya lo agregamos arriba
            }

            $tabs["period_{$period->id}"] = Tab::make($period->name)
                ->modifyQueryUsing(
                    fn(Builder $query) =>
                    $query->where('payroll_period_id', $period->id)
                )
                ->badge(Payroll::where('payroll_period_id', $period->id)->count())
                ->badgeColor('info');
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
