<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AttendanceDayResource;
use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Calcula solo el día de hoy usando el Service AttendanceCalculator
            Action::make('calculate_today')
                ->label('Calcular hoy')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->action(function () {
                    try {
                        $today = now();
                        AttendanceCalculator::applyForDateRange($today, $today);

                        Notification::make()
                            ->title('Cálculo del día completado')
                            ->body('Se calcularon las asistencias de hoy: ' . $today->format('d/m/Y'))
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al calcular')
                            ->body('No se pudo calcular las asistencias de hoy.')
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Calcular asistencias de hoy')
                ->modalDescription('¿Deseas calcular las asistencias solo del día de hoy?'),

            // Calcula usando el Service AttendanceCalculator para un rango de fechas
            Action::make('calculate')
                ->label('Calcular Asistencia')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Fecha de inicio')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->required()
                        ->maxDate(now())
                        ->default(now()->startOfMonth())
                        ->reactive(),

                    DatePicker::make('end_date')
                        ->label('Fecha de fin')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->required()
                        ->maxDate(now())
                        ->default(now())
                        ->afterOrEqual('start_date')
                        ->minDate(fn(Get $get) => $get('start_date'))
                        ->reactive(),

                    Placeholder::make('stats')
                        ->label('Resumen del rango')
                        ->content(function (Get $get) {
                            $start = $get('start_date');
                            $end = $get('end_date');

                            if (!$start || !$end) {
                                return 'Selecciona ambas fechas para ver estadísticas.';
                            }

                            $total = AttendanceDay::whereBetween('date', [$start, $end])->count();
                            $calculated = AttendanceDay::whereBetween('date', [$start, $end])
                                ->where('is_calculated', true)
                                ->count();
                            $notCalculated = $total - $calculated;

                            if ($total === 0) {
                                return '⚠️ ¿No hay registros en este rango.';
                            }

                            return "📊 Total: {$total} | ✅ Calculados: {$calculated} | ⏳ Sin calcular: {$notCalculated}";
                        })
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) {
                    try {
                        $startDate = Carbon::parse($data['start_date']);
                        $endDate = Carbon::parse($data['end_date']);

                        // Contar cuántos registros estaban calculados antes
                        $totalBefore = AttendanceDay::whereBetween('date', [
                            $startDate->toDateString(),
                            $endDate->toDateString()
                        ])->where('is_calculated', true)->count();

                        // Ejecutar el cálculo
                        AttendanceCalculator::applyForDateRange($startDate, $endDate);

                        // Contar cuántos registros están calculados después
                        $totalAfter = AttendanceDay::whereBetween('date', [
                            $startDate->toDateString(),
                            $endDate->toDateString()
                        ])->where('is_calculated', true)->count();

                        $newCalculated = $totalAfter - $totalBefore;
                        $recalculated = $totalBefore;

                        // Enviar notificación de éxito
                        Notification::make()
                            ->title('¡Cálculo completado exitosamente!')
                            ->body("Período: {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')}\n✅ Nuevos calculados: {$newCalculated}\n🔄 Recalculados: {$recalculated}")
                            ->success()
                            ->duration(8000)
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Error calculando asistencias en rango de fechas', [
                            'start_date' => $data['start_date'] ?? null,
                            'end_date' => $data['end_date'] ?? null,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);

                        Notification::make()
                            ->title('Error al calcular asistencias')
                            ->body('No se pudo completar el cálculo. Por favor, verifica las fechas e intenta nuevamente.')
                            ->danger()
                            ->duration(10000)
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Calcular asistencias por rango de fechas')
                ->modalDescription('Esto calculará o recalculará las asistencias de los registros entre las fechas seleccionadas.')
                ->modalSubmitActionLabel('Calcular')
                ->modalWidth('lg'),
        ];
    }

    public function getTabs(): array
    {
        // Optimización: una sola query para todos los contadores
        $stats = AttendanceDay::selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = "on_leave" THEN 1 ELSE 0 END) as on_leave,
            SUM(CASE WHEN is_calculated = 1 THEN 1 ELSE 0 END) as calculated,
            SUM(CASE WHEN is_calculated = 0 THEN 1 ELSE 0 END) as not_calculated
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($stats->total)
                ->badgeColor('gray')
                ->icon('heroicon-o-calendar-days'),

            'present' => Tab::make('Presentes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'present'))
                ->badge($stats->present)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'absent' => Tab::make('Ausentes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'absent'))
                ->badge($stats->absent)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'on_leave' => Tab::make('De permiso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'on_leave'))
                ->badge($stats->on_leave)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'calculated' => Tab::make('Calculados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_calculated', true))
                ->badge($stats->calculated)
                ->badgeColor('info')
                ->icon('heroicon-o-calculator'),

            'not_calculated' => Tab::make('Sin calcular')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_calculated', false))
                ->badge($stats->not_calculated)
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
