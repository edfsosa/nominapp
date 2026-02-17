<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_all')
                ->label('Aprobar Todos')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Todos los Recibos')
                ->modalDescription(function () {
                    $count = Payroll::where('status', 'draft')->count();
                    return "Se aprobarán {$count} recibos en estado Borrador. ¿Desea continuar?";
                })
                ->action(function () {
                    $count = Payroll::where('status', 'draft')
                        ->update([
                            'status' => 'approved',
                            'approved_by_id' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                    Notification::make()
                        ->success()
                        ->title("{$count} recibos aprobados")
                        ->send();
                })
                ->visible(fn() => Payroll::where('status', 'draft')->exists()),

            Action::make('mark_all_paid')
                ->label('Marcar Todos Pagados')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Marcar Todos como Pagados')
                ->modalDescription(function () {
                    $count = Payroll::where('status', 'approved')->count();
                    return "Se marcarán {$count} recibos aprobados como pagados. ¿Desea continuar?";
                })
                ->action(function () {
                    $count = Payroll::where('status', 'approved')
                        ->update(['status' => 'paid']);

                    Notification::make()
                        ->success()
                        ->title("{$count} recibos marcados como pagados")
                        ->send();
                })
                ->visible(fn() => Payroll::where('status', 'approved')->exists()),

            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->withFilename(fn() => 'recibos_nomina_' . now()->format('d_m_Y_H_i_s'))
                        ->withWriterType(Excel::XLSX),
                ])
                ->label('Exportar a Excel')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray'),
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
