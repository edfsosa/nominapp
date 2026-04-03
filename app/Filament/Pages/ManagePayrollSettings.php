<?php

namespace App\Filament\Pages;

use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\PayrollSettings;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;

class ManagePayrollSettings extends SettingsPage
{
    // Configuraciones de la página
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Configuración de Nómina';
    protected static ?string $title = 'Configuración de Nómina';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 4;
    protected static string $settings = PayrollSettings::class;

    /**
     * Define el formulario de configuración de nómina.
     *
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Sección para configuración de horas de trabajo y cálculos relacionados
                Section::make('Horas de Trabajo - Jornada Diurna')
                    ->description('Parámetros para jornada diurna (06:00 - 20:00)')
                    ->icon('heroicon-o-sun')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                // Horas de trabajo mensuales con validación numérica
                                TextInput::make('monthly_hours')
                                    ->label('Horas mensuales')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 240 (30 días × 8 hrs)'),

                                // Horas de trabajo por jornada con validación numérica
                                TextInput::make('daily_hours')
                                    ->label('Horas por jornada')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(24)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 8 hrs (Art. 194)'),

                                // Días laborales por mes con validación numérica
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

                // Sección para configuración de horas de trabajo nocturno y mixto
                Section::make('Horas de Trabajo - Jornada Nocturna y Mixta')
                    ->description('Parámetros para jornadas nocturna (20:00 - 06:00) y mixta')
                    ->icon('heroicon-o-moon')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                // Horas de trabajo mensuales nocturno con validación numérica
                                TextInput::make('monthly_hours_nocturno')
                                    ->label('Horas mensuales nocturno')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 210 (30 días × 7 hrs)'),

                                // Horas de trabajo por jornada nocturna con validación numérica
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
                                // Horas de trabajo mensuales mixto con validación numérica
                                TextInput::make('monthly_hours_mixto')
                                    ->label('Horas mensuales mixto')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(720)
                                    ->required()
                                    ->suffix('hrs')
                                    ->helperText('Default: 225 (30 días × 7.5 hrs)'),

                                // Horas de trabajo por jornada mixta con validación numérica
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

                // Sección para configuración de horas de trabajo en días feriados
                Section::make('Multiplicadores de Horas Extra')
                    ->description('Factores de cálculo según Código del Trabajo (Art. 234)')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('overtime_multiplier_diurno')
                                    ->label('HE Diurnas')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('50% recargo sobre hora diurna → 1.5x'),

                                TextInput::make('overtime_multiplier_nocturno')
                                    ->label('HE Nocturnas (día regular)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('1.30 × 2.0 sobre base diurna → 2.6x'),

                                TextInput::make('overtime_multiplier_holiday')
                                    ->label('HE Diurnas Feriado/Domingo')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('100% recargo sobre hora diurna → 2.0x'),

                                TextInput::make('overtime_multiplier_nocturno_holiday')
                                    ->label('HE Nocturnas Feriado/Domingo')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(10)
                                    ->step(0.1)
                                    ->required()
                                    ->suffix('x')
                                    ->helperText('1.30 × 2.0 sobre base diurna → 2.6x'),
                            ]),
                    ]),

                // Sección para configuración de límites de horas extra
                Section::make('Límites de Horas Extra')
                    ->description('Límites legales de horas extraordinarias (Art. 202)')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->schema([
                        // Máximo horas extra por día con validación numérica
                        TextInput::make('overtime_max_daily_hours')
                            ->label('Máximo horas extra por día')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(24)
                            ->required()
                            ->suffix('hrs')
                            ->helperText('Límite legal: 3 hrs/día'),
                    ]),

                // Sección para configuración de liquidación y finiquito
                Section::make('Liquidación / Finiquito')
                    ->description('Parámetros para cálculo de liquidaciones (Art. 78-100)')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                // Aporte IPS obrero con validación numérica y formato porcentual
                                TextInput::make('ips_employee_rate')
                                    ->label('Aporte IPS Obrero')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.5)
                                    ->required()
                                    ->suffix('%')
                                    ->helperText('Default: 9%'),

                                // Código de la deducción IPS en el catálogo de deducciones
                                TextInput::make('ips_deduction_code')
                                    ->label('Código deducción IPS')
                                    ->required()
                                    ->maxLength(20)
                                    ->helperText('Código del catálogo de deducciones (Default: IPS001)'),

                                // Días de indemnización por año con validación numérica
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

                Section::make('Salarios Mínimos Legales')
                    ->description('Montos fijados por el Ministerio de Trabajo. Actualizar ante cada resolución ministerial.')
                    ->icon('heroicon-o-scale')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('min_salary_monthly')
                                    ->label('Salario mínimo mensual')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->prefix('Gs.')
                                    ->helperText('Para trabajadores mensualizados'),

                                TextInput::make('min_salary_daily_jornal')
                                    ->label('Salario mínimo diario (jornaleros)')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->prefix('Gs.')
                                    ->helperText('Monto diario independiente para trabajadores a jornal'),
                            ]),
                    ])
                    ->collapsed(),

                Section::make('Bonificación Familiar')
                    ->description('Arts. 253-262 Código del Trabajo. Aplica a empleados con hijos que ganen hasta 2 salarios mínimos.')
                    ->icon('heroicon-o-heart')
                    ->schema([
                        TextInput::make('family_bonus_percentage')
                            ->label('Porcentaje por hijo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.5)
                            ->required()
                            ->suffix('%')
                            ->helperText('% del salario mínimo mensual por hijo. Default: 5%'),
                    ])
                    ->collapsed(),

                // Nueva sección para configuración de vacaciones
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

                        CheckboxList::make('vacation_business_days')
                            ->label('Días hábiles para vacaciones')
                            ->helperText('Días que se cuentan como hábiles al calcular vacaciones. Default: Lunes a Sábado.')
                            ->options([
                                1 => 'Lunes',
                                2 => 'Martes',
                                3 => 'Miércoles',
                                4 => 'Jueves',
                                5 => 'Viernes',
                                6 => 'Sábado',
                                7 => 'Domingo',
                            ])
                            ->columns(7)
                            ->required(),
                    ])
                    ->collapsed(),
            ]);
    }
}
