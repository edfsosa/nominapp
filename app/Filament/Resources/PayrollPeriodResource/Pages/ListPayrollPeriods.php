<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_periods')
                ->label('Generar Períodos')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->form([
                    Select::make('frequency')
                        ->label('Frecuencia')
                        ->options([
                            'monthly'  => 'Mensual',
                            'biweekly' => 'Quincenal',
                            'weekly'   => 'Semanal',
                        ])
                        ->native(false)
                        ->required()
                        ->default('monthly')
                        ->live()
                        ->helperText(fn($state) => match ($state) {
                            'monthly'  => 'Un período por mes',
                            'biweekly' => 'Períodos de 14 días',
                            'weekly'   => 'Períodos de 7 días',
                            default    => null,
                        }),

                    DatePicker::make('start_date')
                        ->label('Inicio del rango')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required()
                        ->live()
                        ->helperText('Fecha de inicio del primer período'),

                    DatePicker::make('end_date')
                        ->label('Fin del rango')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required()
                        ->live()
                        ->minDate(fn($get) => $get('start_date'))
                        ->disabled(fn($get) => !$get('start_date'))
                        ->helperText('Fecha hasta la que se generarán períodos consecutivos'),

                    Placeholder::make('periods_preview')
                        ->label('Períodos a generar')
                        ->content(function ($get) {
                            $start     = $get('start_date');
                            $end       = $get('end_date');
                            $frequency = $get('frequency');

                            if (!$start || !$end || !$frequency) {
                                return 'Complete los campos para ver la cantidad estimada.';
                            }

                            $startDate = Carbon::parse($start);
                            $rangeEnd  = Carbon::parse($end);

                            if ($startDate->gt($rangeEnd)) {
                                return 'La fecha de inicio debe ser anterior al fin del rango.';
                            }

                            $count      = 0;
                            $firstStart = null;
                            $lastEnd    = null;
                            $cursor     = $startDate->copy();

                            while ($cursor->lte($rangeEnd)) {
                                $periodEnd = $this->calculateEndDate($cursor, $frequency);
                                if ($count === 0) {
                                    $firstStart = $cursor->copy();
                                }
                                $lastEnd = $periodEnd->copy();
                                $count++;
                                $cursor = $periodEnd->addDay();
                            }

                            if ($count === 0) {
                                return 'El rango no cubre ningún período completo.';
                            }

                            return "{$count} período(s): {$firstStart->format('d/m/Y')} → {$lastEnd->format('d/m/Y')}";
                        }),
                ])
                ->modalWidth('md')
                ->modalHeading('Generar Períodos de Nómina')
                ->modalDescription('Complete los datos para generar automáticamente los períodos de nómina.')
                ->action(function (array $data) {
                    $created  = 0;
                    $skipped  = 0;
                    $rangeEnd = Carbon::parse($data['end_date']);

                    DB::transaction(function () use ($data, $rangeEnd, &$created, &$skipped) {
                        $periodStart = Carbon::parse($data['start_date']);

                        while ($periodStart->lte($rangeEnd)) {
                            $periodEnd = $this->calculateEndDate($periodStart, $data['frequency']);
                            $name      = PayrollPeriod::generateName($data['frequency'], $periodStart, $periodEnd);

                            $exists = PayrollPeriod::where('frequency', $data['frequency'])
                                ->where('start_date', $periodStart->format('Y-m-d'))
                                ->where('end_date', $periodEnd->format('Y-m-d'))
                                ->exists();

                            if (!$exists) {
                                PayrollPeriod::create([
                                    'name'       => $name,
                                    'frequency'  => $data['frequency'],
                                    'start_date' => $periodStart->format('Y-m-d'),
                                    'end_date'   => $periodEnd->format('Y-m-d'),
                                    'status'     => 'draft',
                                ]);
                                $created++;
                            } else {
                                $skipped++;
                            }

                            $periodStart = $periodEnd->copy()->addDay();
                        }
                    });

                    // Notificación de resultado
                    if ($created > 0) {
                        Notification::make()
                            ->success()
                            ->title('Períodos generados')
                            ->body("Se crearon {$created} períodos exitosamente." .
                                ($skipped > 0 ? " Se omitieron {$skipped} períodos duplicados." : ''))
                            ->send();
                    } elseif ($skipped > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Períodos duplicados')
                            ->body("Todos los períodos ya existen. No se creó ninguno nuevo.")
                            ->send();
                    }
                }),

            CreateAction::make()
                ->label('Nuevo Período')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        // Una sola query para todos los badges, evitando 7 queries separadas.
        $counts = PayrollPeriod::selectRaw('
            COUNT(*) as total,
            SUM(status = "draft") as draft,
            SUM(status = "processing") as processing,
            SUM(status = "closed") as closed,
            SUM(frequency = "monthly") as monthly,
            SUM(frequency = "biweekly") as biweekly,
            SUM(frequency = "weekly") as weekly
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts->total),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge($counts->draft)
                ->badgeColor('gray'),

            'processing' => Tab::make('En Proceso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'processing'))
                ->badge($counts->processing)
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerrados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge($counts->closed)
                ->badgeColor('success'),

            'monthly' => Tab::make('Mensuales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'monthly'))
                ->badge($counts->monthly)
                ->badgeColor('info'),

            'biweekly' => Tab::make('Quincenales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'biweekly'))
                ->badge($counts->biweekly)
                ->badgeColor('info'),

            'weekly' => Tab::make('Semanales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'weekly'))
                ->badge($counts->weekly)
                ->badgeColor('info'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }

    // Calcula la fecha de fin según la frecuencia del período.
    // Centralizado para evitar duplicación y garantizar consistencia.
    private function calculateEndDate(Carbon $start, string $frequency): Carbon
    {
        return match ($frequency) {
            'monthly'  => $start->copy()->endOfMonth(),
            'biweekly' => $start->copy()->addDays(13), // 14 días (día 0 al 13)
            'weekly'   => $start->copy()->addDays(6),  // 7 días (día 0 al 6)
            default    => $start->copy()->endOfMonth(),
        };
    }
}
