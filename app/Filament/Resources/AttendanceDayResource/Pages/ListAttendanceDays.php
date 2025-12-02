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
            // Calcula usando el Service AttendanceCalculator para un rango de fechas
            Action::make('calculate')
                ->label('Calcular Asistencia')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->form([
                    DatePicker::make('start_date')
                        ->label('Fecha de inicio')
                        ->required()
                        ->maxDate(now())
                        ->default(now()->startOfMonth())
                        ->reactive(),
                    DatePicker::make('end_date')
                        ->label('Fecha de fin')
                        ->required()
                        ->maxDate(now())
                        ->default(now())
                        ->afterOrEqual('start_date')
                        ->reactive(),
                    Placeholder::make('stats')
                        ->label('')
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
                                return '⚠️ No hay registros en este rango.';
                            }

                            return "📊 Total: {$total} | ✅ Calculados: {$calculated} | ⏳ Sin calcular: {$notCalculated}";
                        }),
                ])
                ->action(function (array $data) {
                    try {
                        $startDate = Carbon::parse($data['start_date']);
                        $endDate = Carbon::parse($data['end_date']);

                        $totalBefore = AttendanceDay::whereBetween('date', [
                            $startDate->toDateString(),
                            $endDate->toDateString()
                        ])->where('is_calculated', true)->count();

                        AttendanceCalculator::applyForDateRange($startDate, $endDate);

                        $totalAfter = AttendanceDay::whereBetween('date', [
                            $startDate->toDateString(),
                            $endDate->toDateString()
                        ])->where('is_calculated', true)->count();

                        $calculated = $totalAfter - $totalBefore;
                        $recalculated = $totalBefore;

                        Notification::make()
                            ->title('¡Cálculo completado!')
                            ->body("Se procesaron desde {$startDate->format('d/m/Y')} hasta {$endDate->format('d/m/Y')}: {$calculated} calculado(s), {$recalculated} recalculado(s).")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Log::error('Error calculando rango: ' . $e->getMessage());

                        Notification::make()
                            ->title('Error al calcular')
                            ->body('No se pudo completar el cálculo. Intenta nuevamente.')
                            ->danger()
                            ->send();
                    }
                })
                ->requiresConfirmation()
                ->modalHeading('Calcular asistencias por rango')
                ->modalSubmitActionLabel('Calcular'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('Todos')
                ->badge(AttendanceDay::count()),
            'present' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'present'))
                ->label('Presentes')
                ->badge(AttendanceDay::query()->where('status', 'present')->count())
                ->badgeColor('success'),
            'absent' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'absent'))
                ->label('Ausentes')
                ->badge(AttendanceDay::query()->where('status', 'absent')->count())
                ->badgeColor('danger'),
            'on_leave' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'on_leave'))
                ->label('De permiso')
                ->badge(AttendanceDay::query()->where('status', 'on_leave')->count())
                ->badgeColor('warning'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
