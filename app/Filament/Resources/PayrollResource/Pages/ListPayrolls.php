<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
        // Una sola query para todos los badges.
        $counts = Payroll::selectRaw('
            COUNT(*) as total,
            SUM(status = "draft") as draft,
            SUM(status = "approved") as approved,
            SUM(status = "paid") as paid
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts->total),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge($counts->draft)
                ->badgeColor('gray'),

            'approved' => Tab::make('Aprobados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved'))
                ->badge($counts->approved)
                ->badgeColor('success'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'paid'))
                ->badge($counts->paid)
                ->badgeColor('info'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
