<?php

namespace App\Filament\Pages;

use Filament\Forms\Form;
use Filament\Pages\SettingsPage;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;

class ManageGeneralSettings extends SettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configuración General';

    protected static ?string $title = 'Configuración General';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static string $settings = GeneralSettings::class;

    /**
     * Define el formulario de configuración general.
     *
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información de la Empresa')
                    ->description('Datos generales de la empresa')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Nombre')
                            ->placeholder('Mi Empresa S.A.')
                            ->maxLength(255)
                            ->required(),

                        FileUpload::make('company_logo')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('company')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/svg+xml'])
                            ->downloadable()
                            ->previewable()
                            ->helperText('Tamaño máximo 2 MB (JPEG, PNG, SVG)'),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('company_ruc')
                                    ->label('RUC')
                                    ->placeholder('80000000-0')
                                    ->regex('/^\d{8}-\d{1}$/')
                                    ->maxLength(50),

                                TextInput::make('company_employer_number')
                                    ->label('Nro. Patronal')
                                    ->placeholder('137678')
                                    ->maxLength(20)
                                    ->helperText('Número patronal del Ministerio de Trabajo'),
                            ]),

                        Grid::make(3)
                            ->schema([
                                TextInput::make('company_phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->placeholder('971123456')
                                    ->minLength(7)
                                    ->maxLength(15),

                                TextInput::make('company_email')
                                    ->label('Correo Electrónico')
                                    ->placeholder('correo@empresa.com')
                                    ->email()
                                    ->maxLength(100),

                                TextInput::make('company_city')
                                    ->label('Ciudad')
                                    ->placeholder('Asunción')
                                    ->maxLength(100)
                                    ->helperText('Ciudad para documentos oficiales'),
                            ]),

                        Textarea::make('company_address')
                            ->label('Dirección')
                            ->placeholder('Av. Principal 123, Ciudad, País')
                            ->rows(1)
                            ->maxLength(500),
                    ]),

                Section::make('Configuración Laboral')
                    ->description('Parámetros de trabajo y horarios')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('timezone')
                                    ->label('Zona horaria')
                                    ->options([
                                        'America/Asuncion' => 'América/Asunción (UTC -3)',
                                        'America/Argentina/Buenos_Aires' => 'América/Buenos Aires (UTC -3)',
                                        'America/Sao_Paulo' => 'América/São Paulo (UTC -3)',
                                        'America/Montevideo' => 'América/Montevideo (UTC -3)',
                                        'America/Santiago' => 'América/Santiago (UTC -4)',
                                    ])
                                    ->native(false)
                                    ->default('America/Asuncion')
                                    ->searchable()
                                    ->helperText('Zona horaria para el cálculo de fechas y horas'),

                                TextInput::make('working_hours_per_week')
                                    ->label('Horas de trabajo por semana')
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(168)
                                    ->default(48)
                                    ->helperText('Cantidad de horas laborales en una semana'),
                            ]),
                    ]),

                Section::make('Configuración de Préstamos')
                    ->description('Parámetros para préstamos y adelantos')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('max_loan_amount')
                            ->label('Monto máximo de préstamo')
                            ->numeric()
                            ->minValue(0)
                            ->default(5000000)
                            ->prefix('Gs.')
                            ->helperText('Monto máximo que se puede prestar a un empleado'),
                    ]),
            ]);
    }
}
