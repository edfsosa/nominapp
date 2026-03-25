<?php

namespace App\Filament\Resources\AttendanceEventResource\Pages;

use App\Exports\AttendanceEventsExport;
use App\Filament\Resources\AttendanceEventResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/** Página única de gestión de marcaciones: listado, creación y edición en modal. */
class ManageAttendanceEvents extends ManageRecords
{
    protected static string $resource = AttendanceEventResource::class;

    /**
     * Acciones del encabezado: actualizar, exportar y crear marcación.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Actualizar')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn() => $this->dispatch('$refresh')),

            Action::make('export_excel')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar marcaciones a Excel')
                ->modalDescription('Se exportarán las marcaciones según los filtros y tab activos en la tabla.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    $filters = $this->resolveExportFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('El listado de marcaciones se está descargando.')
                        ->send();

                    return Excel::download(
                        new AttendanceEventsExport(...$filters),
                        'marcaciones_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),
        ];
    }

    /**
     * Extrae y normaliza los filtros activos de la tabla para pasarlos al export.
     * Los filtros múltiples (SelectFilter con ->multiple()) usan la clave 'values'.
     * El tab activo puede imponer filtros adicionales (hoy / tipo de evento).
     *
     * @return array{
     *   employeeIds: int[]|null,
     *   branchIds:   int[]|null,
     *   eventTypes:  string[]|null,
     *   fromDate:    string|null,
     *   toDate:      string|null,
     *   onlyToday:   bool
     * }
     */
    private function resolveExportFilters(): array
    {
        $f = $this->tableFilters ?? [];

        $employeeIds = array_values(array_filter($f['employee_id']['values'] ?? []));
        $branchIds   = array_values(array_filter($f['branch_id']['values']   ?? []));
        $eventTypes  = array_values(array_filter($f['event_type']['values']  ?? []));
        $fromDate    = $f['recorded_at']['recorded_from'] ?? null;
        $toDate      = $f['recorded_at']['recorded_until'] ?? null;
        $onlyToday   = false;

        // El tab activo puede sobrescribir los filtros de tipo/fecha
        $tab = $this->activeTab;

        if ($tab === 'today') {
            $onlyToday = true;
        } elseif (in_array($tab, ['check_in', 'check_out'], true)) {
            // Si hay tab de tipo activo, prevalece sobre el filtro de columna
            $eventTypes = [$tab];
        }

        return [
            'employeeIds' => $employeeIds ?: null,
            'branchIds'   => $branchIds   ?: null,
            'eventTypes'  => $eventTypes  ?: null,
            'fromDate'    => $fromDate    ?: null,
            'toDate'      => $toDate      ?: null,
            'onlyToday'   => $onlyToday,
        ];
    }

    /**
     * Tabs de filtrado por tipo de evento y marcaciones de hoy.
     * Usa una sola query agregada para evitar N+1.
     *
     * @return array<string, \Filament\Resources\Components\Tab>
     */
    public function getTabs(): array
    {
        $counts = DB::table('attendance_events')
            ->selectRaw('
                COUNT(*) as all_count,
                SUM(CASE WHEN event_type = "check_in" THEN 1 ELSE 0 END) as check_in_count,
                SUM(CASE WHEN event_type = "check_out" THEN 1 ELSE 0 END) as check_out_count,
                SUM(CASE WHEN DATE(recorded_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
            ')
            ->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts->all_count)
                ->badgeColor('gray')
                ->icon('heroicon-o-finger-print'),

            'today' => Tab::make('Hoy')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereDate('recorded_at', now()))
                ->badge($counts->today_count)
                ->badgeColor('primary')
                ->icon('heroicon-o-calendar-days'),

            'check_in' => Tab::make('Entradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_in'))
                ->badge($counts->check_in_count)
                ->badgeColor('success')
                ->icon('heroicon-o-arrow-right-on-rectangle'),

            'check_out' => Tab::make('Salidas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('event_type', 'check_out'))
                ->badge($counts->check_out_count)
                ->badgeColor('danger')
                ->icon('heroicon-o-arrow-left-on-rectangle'),
        ];
    }

    /**
     * Tab activo por defecto al cargar la página.
     *
     * @return string|int|null
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'today';
    }
}
