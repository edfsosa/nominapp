<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Exports\AguinaldoProvisionExport;
use App\Filament\Resources\AguinaldoPeriodResource;
use App\Models\AguinaldoPeriod;
use App\Services\AguinaldoService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Página de reporte de provisión mensual de aguinaldo.
 *
 * Muestra, mes a mes, cuánto debe la empresa en concepto de aguinaldo acumulado
 * por empleado, calculado como sum(base_salary + ips_perceptions) / 12.
 */
class ProvisionReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = AguinaldoPeriodResource::class;
    protected static string $view = 'filament.resources.aguinaldo-period-resource.pages.provision-report';
    protected static ?string $title = 'Provisión de Aguinaldo';

    /** @var AguinaldoPeriod Período inyectado desde la ruta. */
    public AguinaldoPeriod $record;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Exportar provisión a Excel?')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    $upToMonth = $this->getUpToMonth();

                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('La planilla de provisión se está descargando.')
                        ->send();

                    return Excel::download(
                        new AguinaldoProvisionExport($this->record, $upToMonth),
                        'provision_aguinaldo_' . $this->record->year . '_mes' . $upToMonth . '_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            Action::make('back')
                ->label('Volver al Período')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AguinaldoPeriodResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->columns([
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable(),

                TextColumn::make('full_name')
                    ->label('Empleado')
                    ->getStateUsing(fn($record) => $record->first_name . ' ' . $record->last_name)
                    ->searchable(query: fn(Builder $q, string $s) => $q
                        ->where('employees.first_name', 'like', "%{$s}%")
                        ->orWhere('employees.last_name', 'like', "%{$s}%")
                    ),

                TextColumn::make('months_worked')
                    ->label('Meses')
                    ->alignCenter()
                    ->summarize(Sum::make()->label('—')),

                TextColumn::make('total_earned')
                    ->label('Total Devengado')
                    ->money('PYG', locale: 'es_PY')
                    ->alignRight()
                    ->summarize(Sum::make()->label('Total')->money('PYG', locale: 'es_PY')),

                TextColumn::make('provision')
                    ->label('Provisión Aguinaldo')
                    ->money('PYG', locale: 'es_PY')
                    ->alignRight()
                    ->color('success')
                    ->summarize(Sum::make()->label('Total empresa')->money('PYG', locale: 'es_PY')),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->label('Acumulado hasta')
                    ->options([
                        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                    ])
                    ->default((string) now()->month)
                    ->selectablePlaceholder(false),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->persistFiltersInSession()
            ->paginated(false);
    }

    /**
     * Construye la query de provisión usando el mes del filtro activo.
     */
    private function buildQuery(): Builder
    {
        return app(AguinaldoService::class)
            ->provisionQuery($this->record, $this->getUpToMonth());
    }

    /**
     * Resuelve el mes seleccionado en el filtro (default: mes actual).
     */
    private function getUpToMonth(): int
    {
        return (int) ($this->tableFilters['month']['value'] ?? now()->month);
    }
}
