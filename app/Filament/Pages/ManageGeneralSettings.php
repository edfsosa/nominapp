<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\SettingsPage;

class ManageGeneralSettings extends SettingsPage
{
    // Configuraciones de la página
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Configuración General';

    protected static ?string $title = 'Configuración General';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 3;

    protected static string $settings = GeneralSettings::class;

    /**
     * Define el formulario de configuración general.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // Sección de información de la empresa
                Section::make('Información de la Empresa')
                    ->description('Datos generales de la empresa')
                    ->icon('heroicon-o-building-office-2')
                    ->schema([
                        // Nombre de la empresa, con validación y longitud máxima
                        TextInput::make('company_name')
                            ->label('Nombre')
                            ->placeholder('Mi Empresa S.A.')
                            ->maxLength(255)
                            ->required(),

                        // Logo de la empresa, con opciones de edición y validación
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

                        // RUC y Nro. Patronal en una cuadrícula de 2 columnas
                        Grid::make(2)
                            ->schema([
                                // RUC con formato específico
                                TextInput::make('company_ruc')
                                    ->label('RUC')
                                    ->placeholder('80012345-6')
                                    ->regex('/^\d{1,8}-\d$/')
                                    ->maxLength(20)
                                    ->validationMessages(['regex' => 'El RUC debe tener el formato número-dígito verificador. Ej: 80012345-6 o 1234567-1.']),

                                // Nro. Patronal con longitud máxima
                                TextInput::make('company_employer_number')
                                    ->label('Nro. Patronal')
                                    ->placeholder('137678')
                                    ->maxLength(20)
                                    ->helperText('Número patronal del Ministerio de Trabajo'),
                            ]),

                        // Teléfono, correo electrónico y ciudad en una cuadrícula de 3 columnas
                        Grid::make(3)
                            ->schema([
                                // Teléfono con prefijo y validación de longitud
                                TextInput::make('company_phone')
                                    ->label('Teléfono')
                                    ->placeholder('0981123456')
                                    ->maxLength(10)
                                    ->regex('/^0\d{8,9}$/')
                                    ->helperText('Sin espacios ni guiones. Ej: 0981123456')
                                    ->validationMessages(['regex' => 'Ingrese un número válido de Paraguay: móvil (09XXXXXXXX) o fijo (021XXXXXX / 0XXXXXXXX).']),

                                // Correo electrónico con validación
                                TextInput::make('company_email')
                                    ->label('Correo Electrónico')
                                    ->placeholder('correo@empresa.com')
                                    ->email()
                                    ->maxLength(100),

                                // Ciudad con longitud máxima
                                TextInput::make('company_city')
                                    ->label('Ciudad')
                                    ->placeholder('Asunción')
                                    ->maxLength(100)
                                    ->helperText('Ciudad para documentos oficiales'),
                            ]),

                        // Dirección en un área de texto
                        Textarea::make('company_address')
                            ->label('Dirección')
                            ->placeholder('Av. Principal 123, Ciudad, País')
                            ->rows(1)
                            ->maxLength(500)
                            ->helperText('Dirección para documentos oficiales'),
                    ]),

                // Nueva sección para configuración laboral
                Section::make('Configuración Laboral')
                    ->description('Parámetros de trabajo y horarios')
                    ->icon('heroicon-o-clock')
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
                    ]),

                Section::make('Configuración de Contratos')
                    ->description('Parámetros para alertas de vencimiento de contratos')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        TextInput::make('contract_alert_days')
                            ->label('Días de anticipación para alertas')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(180)
                            ->default(30)
                            ->suffix('días')
                            ->helperText('Cantidad de días antes del vencimiento para mostrar alertas de contratos'),
                    ]),

                Section::make('Registro Facial de Empleados')
                    ->description('Configuración para el auto-registro facial')
                    ->icon('heroicon-o-finger-print')
                    ->schema([
                        TextInput::make('face_enrollment_expiry_hours')
                            ->label('Horas de validez del enlace')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(720)
                            ->default(48)
                            ->suffix('horas')
                            ->helperText('Tiempo de validez del enlace de registro facial enviado al empleado'),
                    ]),
            ]);
    }
}
