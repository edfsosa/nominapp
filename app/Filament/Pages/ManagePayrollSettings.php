<?php

namespace App\Filament\Pages;

use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\PayrollSettings;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;

class ManagePayrollSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Configuración de Nómina';

    protected static ?string $title = 'Configuración de Nómina';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 4;

    protected static string $settings = PayrollSettings::class;

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Horas de Trabajo - Jornada Diurna')
                    ->description('Parámetros para jornada diurna (06:00 - 20:00)')
                    ->icon('heroicon-o-sun')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('monthly_hours')
                                    ->label('Horas mensuales')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 240 (30 días × 8 hrs)'),

                                TextInput::make('daily_hours')
                                    ->label('Horas por jornada')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(24)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 8 hrs (Art. 194)'),

                                TextInput::make('days_per_month')
                                    ->label('Días laborales/mes')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(31)
                                    ->required()
                                    ->suffix('días')
                                    ->helperText('Default: 30 días'),
                            ]),
                    ]),

                Section::make('Horas de Trabajo - Jornada Nocturna y Mixta')
                    ->description('Parámetros para jornadas nocturna (20:00 - 06:00) y mixta')
                    ->icon('heroicon-o-moon')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('monthly_hours_nocturno')
                                    ->label('Horas mensuales nocturno')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 210 (30 días × 7 hrs)'),

                                TextInput::make('daily_hours_nocturno')
                                    ->label('Horas por jornada nocturna')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(24)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 7 hrs (Art. 196)'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('monthly_hours_mixto')
                                    ->label('Horas mensuales mixto')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 225 (30 días × 7.5 hrs)'),

                                TextInput::make('daily_hours_mixto')
                                    ->label('Horas por jornada mixta')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(24)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 7.5 hrs (Art. 197)'),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Multiplicadores de Horas Extra')
                    ->description('Factores de cálculo según Código del Trabajo (Art. 234)')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('overtime_multiplier_diurno')
                                    ->label('HE Diurnas')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('50% recargo → 1.5x'),

                                TextInput::make('overtime_multiplier_nocturno')
                                    ->label('HE Nocturnas')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('1.3 × 2.0 = 2.6x'),

                                TextInput::make('overtime_multiplier_holiday')
                                    ->label('HE Feriado/Domingo')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('100% recargo → 2.0x'),
                            ]),
                    ]),

                Section::make('Límites de Horas Extra')
                    ->description('Límites legales de horas extraordinarias (Art. 202)')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        TextInput::make('overtime_max_daily_hours')
                            ->label('Máximo horas extra por día')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->required()
                            ->suffix('hrs')
                            ->helperText('Límite legal: 3 hrs/día'),
                    ]),

                Section::make('Liquidación / Finiquito')
                    ->description('Parámetros para cálculo de liquidaciones (Art. 78-100)')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('ips_employee_rate')
                                    ->label('Aporte IPS Obrero')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.5)
                                    ->required()
                                    ->suffix('%')
                                    ->helperText('Default: 9%'),

                                TextInput::make('indemnizacion_days_per_year')
                                    ->label('Días indemnización por año')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(30)
                                    ->required()
                                    ->suffix('días')
                                    ->helperText('Default: 15 días/año'),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Vacaciones')
                    ->description('Parámetros generales de vacaciones')
                    ->icon('heroicon-o-calendar-days')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('vacation_min_consecutive_days')
                                    ->label('Mínimo días consecutivos')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(30)
                                    ->required()
                                    ->suffix('días')
                                    ->helperText('Fraccionamiento mínimo (Default: 6)'),

                                TextInput::make('vacation_min_years_service')
                                    ->label('Años mínimos de servicio')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(10)
                                    ->required()
                                    ->suffix('años')
                                    ->helperText('Para tener derecho a vacaciones (Default: 1)'),
                            ]),
                    ])
                    ->collapsed(),
            ]);
    }
}
