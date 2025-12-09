<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

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
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Auto-calcular end_date si hay start_date
                            $startDate = $get('start_date');
                            if ($startDate) {
                                $start = \Carbon\Carbon::parse($startDate);
                                $end = match ($state) {
                                    'monthly' => $start->copy()->endOfMonth(),
                                    'biweekly' => $start->copy()->addDays(13), // 14 días (0-13)
                                    'weekly' => $start->copy()->addDays(6),    // 7 días (0-6)
                                    default => $start->copy()->endOfMonth(),
                                };
                                $set('end_date', $end->format('Y-m-d'));
                            }
                        })
                        ->helperText(fn($state) => match ($state) {
                            'monthly' => 'Un período por mes',
                            'biweekly' => 'Períodos de 14 días',
                            'weekly' => 'Períodos de 7 días',
                            default => null,
                        }),

                    DatePicker::make('start_date')
                        ->label('Fecha de Inicio')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Auto-calcular end_date basado en frequency
                            $frequency = $get('frequency');
                            if ($frequency && $state) {
                                $start = \Carbon\Carbon::parse($state);
                                $end = match ($frequency) {
                                    'monthly' => $start->copy()->endOfMonth(),
                                    'biweekly' => $start->copy()->addDays(13), // 14 días
                                    'weekly' => $start->copy()->addDays(6),    // 7 días
                                    default => $start->copy()->endOfMonth(),
                                };
                                $set('end_date', $end->format('Y-m-d'));
                            }
                        }),

                    DatePicker::make('end_date')
                        ->label('Fecha de Fin')
                        ->displayFormat('d/m/Y')
                        ->native(false)
                        ->closeOnDateSelection()
                        ->required()
                        ->minDate(fn($get) => $get('start_date'))
                        ->disabled(fn($get) => !$get('start_date'))
                        ->helperText('Se calcula automáticamente según la frecuencia'),

                    Select::make('quantity')
                        ->label('Cantidad de Períodos')
                        ->options([
                            1  => '1 período',
                            2  => '2 períodos',
                            3  => '3 períodos',
                            4  => '4 períodos',
                            6  => '6 períodos',
                            12 => '12 períodos',
                            24 => '24 períodos',
                            26 => '26 períodos (medio año quincenal)',
                            52 => '52 períodos (año completo semanal)',
                        ])
                        ->native(false)
                        ->default(1)
                        ->required()
                        ->helperText('Cantidad de períodos consecutivos a generar'),
                ])
                ->modalWidth('md')
                ->modalHeading('Generar Períodos de Nómina')
                ->modalDescription('Complete los datos para generar automáticamente los períodos de nómina.')
                ->action(function (array $data) {
                    $created = 0;
                    $skipped = 0;

                    $startDate = \Carbon\Carbon::parse($data['start_date']);
                    $endDate = \Carbon\Carbon::parse($data['end_date']);

                    for ($i = 0; $i < $data['quantity']; $i++) {
                        // Generar nombre automático
                        $name = match ($data['frequency']) {
                            'monthly' => $startDate->locale('es')->isoFormat('MMMM YYYY'),
                            'biweekly' => 'Quincena ' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                            'weekly' => 'Semana ' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                            default => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                        };

                        // Verificar si ya existe
                        $exists = PayrollPeriod::where('frequency', $data['frequency'])
                            ->where('start_date', $startDate->format('Y-m-d'))
                            ->where('end_date', $endDate->format('Y-m-d'))
                            ->exists();

                        if (!$exists) {
                            PayrollPeriod::create([
                                'name' => $name,
                                'frequency' => $data['frequency'],
                                'start_date' => $startDate->format('Y-m-d'),
                                'end_date' => $endDate->format('Y-m-d'),
                                'status' => 'draft',
                            ]);
                            $created++;
                        } else {
                            $skipped++;
                        }

                        // Calcular siguiente período
                        $startDate = $endDate->copy()->addDay();
                        $endDate = match ($data['frequency']) {
                            'monthly' => $startDate->copy()->endOfMonth(),
                            'biweekly' => $startDate->copy()->addDays(13), // 14 días
                            'weekly' => $startDate->copy()->addDays(6),    // 7 días
                            default => $startDate->copy()->endOfMonth(),
                        };
                    }

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
        return [
            'all' => Tab::make('Todos')
                ->badge(PayrollPeriod::count()),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge(PayrollPeriod::where('status', 'draft')->count())
                ->badgeColor('gray'),

            'processing' => Tab::make('En Proceso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'processing'))
                ->badge(PayrollPeriod::where('status', 'processing')->count())
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerrados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge(PayrollPeriod::where('status', 'closed')->count())
                ->badgeColor('success'),

            'monthly' => Tab::make('Mensuales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'monthly'))
                ->badge(PayrollPeriod::where('frequency', 'monthly')->count())
                ->badgeColor('info'),

            'biweekly' => Tab::make('Quincenales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'biweekly'))
                ->badge(PayrollPeriod::where('frequency', 'biweekly')->count())
                ->badgeColor('info'),

            'weekly' => Tab::make('Semanales')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('frequency', 'weekly'))
                ->badge(PayrollPeriod::where('frequency', 'weekly')->count())
                ->badgeColor('info'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
