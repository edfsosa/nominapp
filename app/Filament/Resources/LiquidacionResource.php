<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LiquidacionResource\Pages;
use App\Filament\Resources\LiquidacionResource\RelationManagers\ItemsRelationManager;
use App\Models\Employee;
use App\Models\Liquidacion;
use App\Services\LiquidacionService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LiquidacionResource extends Resource
{
    protected static ?string $model = Liquidacion::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Liquidaciones';
    protected static ?string $label = 'Liquidación';
    protected static ?string $pluralLabel = 'Liquidaciones';
    protected static ?string $slug = 'liquidaciones';
    protected static ?string $navigationIcon = 'heroicon-o-document-minus';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos del Empleado')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->options(function () {
                                return Employee::where('status', 'active')
                                    ->get()
                                    ->mapWithKeys(fn($e) => [$e->id => "{$e->full_name} - CI: {$e->ci}"]);
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $employee = Employee::find($state);
                                    if ($employee) {
                                        $set('hire_date', $employee->hire_date?->format('Y-m-d'));
                                        $set('base_salary', $employee->base_salary);
                                    }
                                }
                            })
                            ->disabled(fn(?Liquidacion $record) => $record && !$record->isDraft())
                            ->columnSpan(2),

                        Placeholder::make('employee_hire_date')
                            ->label('Fecha de Ingreso')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if ($employeeId) {
                                    $employee = Employee::find($employeeId);
                                    return $employee?->hire_date?->format('d/m/Y') ?? '-';
                                }
                                return '-';
                            })
                            ->columnSpan(1),

                        Placeholder::make('employee_salary')
                            ->label('Salario Base')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if ($employeeId) {
                                    $employee = Employee::find($employeeId);
                                    return $employee ? Liquidacion::formatCurrency($employee->base_salary) : '-';
                                }
                                return '-';
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Datos de la Desvinculación')
                    ->schema([
                        DatePicker::make('termination_date')
                            ->label('Fecha de Desvinculación')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->disabled(fn(?Liquidacion $record) => $record && !$record->isDraft())
                            ->columnSpan(1),

                        Select::make('termination_type')
                            ->label('Tipo de Desvinculación')
                            ->options(Liquidacion::getTerminationTypeOptions())
                            ->native(false)
                            ->required()
                            ->reactive()
                            ->disabled(fn(?Liquidacion $record) => $record && !$record->isDraft())
                            ->columnSpan(1),

                        Toggle::make('preaviso_otorgado')
                            ->label('¿Se otorgó preaviso al empleado?')
                            ->helperText('Si el empleador dio aviso anticipado, no se paga preaviso')
                            ->default(false)
                            ->visible(fn(Get $get) => Liquidacion::includesPreaviso($get('termination_type') ?? ''))
                            ->disabled(fn(?Liquidacion $record) => $record && !$record->isDraft())
                            ->columnSpan(2),

                        Placeholder::make('components_info')
                            ->label('Componentes a calcular')
                            ->content(function (Get $get) {
                                $type = $get('termination_type');
                                if (!$type) {
                                    return 'Seleccione un tipo de desvinculación';
                                }

                                $components = [];
                                if (Liquidacion::includesPreaviso($type) && !$get('preaviso_otorgado')) {
                                    $components[] = 'Preaviso';
                                }
                                if (Liquidacion::includesIndemnizacion($type)) {
                                    $components[] = 'Indemnización';
                                }
                                $components[] = 'Vacaciones proporcionales';
                                $components[] = 'Aguinaldo proporcional';
                                $components[] = 'Salario pendiente';

                                return 'Incluye: ' . implode(' + ', $components);
                            })
                            ->columnSpan(2),

                        Textarea::make('termination_reason')
                            ->label('Motivo de Desvinculación')
                            ->rows(2)
                            ->maxLength(65535)
                            ->disabled(fn(?Liquidacion $record) => $record && !$record->isDraft())
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Notas adicionales sobre esta liquidación')
                            ->rows(2)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('CI copiado')
                    ->weight('bold'),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('termination_date')
                    ->label('Fecha Egreso')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('termination_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'unjustified_dismissal' => 'danger',
                        'justified_dismissal'   => 'warning',
                        'resignation'           => 'info',
                        'mutual_agreement'      => 'primary',
                        'contract_end'          => 'gray',
                        default                 => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => Liquidacion::getTerminationTypeLabel($state))
                    ->sortable(),

                TextColumn::make('total_haberes')
                    ->label('Haberes')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable()
                    ->color('success'),

                TextColumn::make('total_deductions')
                    ->label('Descuentos')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable()
                    ->color('danger'),

                TextColumn::make('net_amount')
                    ->label('Neto a Pagar')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft'      => 'gray',
                        'calculated' => 'warning',
                        'closed'     => 'success',
                        default      => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft'      => 'Borrador',
                        'calculated' => 'Calculada',
                        'closed'     => 'Cerrada',
                        default      => $state,
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('termination_type')
                    ->label('Tipo')
                    ->options(Liquidacion::getTerminationTypeOptions())
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft'      => 'Borrador',
                        'calculated' => 'Calculada',
                        'closed'     => 'Cerrada',
                    ])
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('calculate')
                    ->label('Calcular')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Calcular Liquidación')
                    ->modalDescription(
                        fn(Liquidacion $record) =>
                        "¿Calcular la liquidación de {$record->employee->full_name}? " .
                            "Tipo: " . Liquidacion::getTerminationTypeLabel($record->termination_type)
                    )
                    ->action(function (Liquidacion $record, LiquidacionService $service) {
                        try {
                            $service->calculate($record);

                            Notification::make()
                                ->success()
                                ->title('Liquidación calculada')
                                ->body("Neto a pagar: {$record->fresh()->formatted_net_amount}")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al calcular')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn(Liquidacion $record) => $record->isDraft()),

                Action::make('close')
                    ->label('Cerrar')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar Liquidación y Desactivar Empleado')
                    ->modalDescription(
                        fn(Liquidacion $record) =>
                        "¿Cerrar la liquidación de {$record->employee->full_name}? " .
                            "El empleado será marcado como INACTIVO y los préstamos pendientes serán cancelados. " .
                            "Esta acción no se puede deshacer."
                    )
                    ->action(function (Liquidacion $record, LiquidacionService $service) {
                        $service->close($record);

                        Notification::make()
                            ->success()
                            ->title('Liquidación cerrada')
                            ->body("El empleado {$record->employee->full_name} ha sido desactivado.")
                            ->send();
                    })
                    ->visible(fn(Liquidacion $record) => $record->isCalculated()),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Liquidacion $record) => route('liquidaciones.download', $record))
                    ->openUrlInNewTab()
                    ->visible(fn(Liquidacion $record) => $record->pdf_path !== null),

                EditAction::make()
                    ->visible(fn(Liquidacion $record) => $record->isDraft()),

                DeleteAction::make()
                    ->visible(fn(Liquidacion $record) => $record->isDraft()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $deleted = 0;
                            foreach ($records as $record) {
                                if ($record->isDraft()) {
                                    $record->delete();
                                    $deleted++;
                                }
                            }
                            if ($deleted > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("Se eliminaron {$deleted} liquidaciones en borrador")
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay liquidaciones registradas')
            ->emptyStateDescription('Comience creando una liquidación para calcular el finiquito de un empleado.')
            ->emptyStateIcon('heroicon-o-document-minus');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Datos del Empleado')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.full_name')
                                ->label('Nombre Completo'),
                            TextEntry::make('employee.ci')
                                ->label('Cédula de Identidad')
                                ->copyable(),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('employee.position.name')
                                ->label('Cargo')
                                ->badge()
                                ->color('info')
                                ->default('N/A'),
                            TextEntry::make('employee.position.department.name')
                                ->label('Departamento')
                                ->badge()
                                ->color('primary')
                                ->default('N/A'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Datos de la Desvinculación')
                    ->schema([
                        Group::make([
                            TextEntry::make('hire_date')
                                ->label('Fecha de Ingreso')
                                ->date('d/m/Y'),
                            TextEntry::make('termination_date')
                                ->label('Fecha de Egreso')
                                ->date('d/m/Y'),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('termination_type')
                                ->label('Tipo de Desvinculación')
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'unjustified_dismissal' => 'danger',
                                    'justified_dismissal'   => 'warning',
                                    'resignation'           => 'info',
                                    'mutual_agreement'      => 'primary',
                                    'contract_end'          => 'gray',
                                    default                 => 'gray',
                                })
                                ->formatStateUsing(fn(string $state): string => Liquidacion::getTerminationTypeLabel($state)),
                            TextEntry::make('seniority')
                                ->label('Antigüedad')
                                ->state(fn(Liquidacion $record) => "{$record->years_of_service} año(s), " . ($record->months_of_service % 12) . " mes(es)"),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('base_salary')
                                ->label('Salario Base')
                                ->money('PYG', locale: 'es_PY'),
                            TextEntry::make('average_salary_6m')
                                ->label('Promedio Últimos 6 Meses')
                                ->money('PYG', locale: 'es_PY'),
                        ])->columns(2),
                        TextEntry::make('termination_reason')
                            ->label('Motivo')
                            ->placeholder('Sin motivo especificado')
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('Cálculo de Haberes')
                    ->schema([
                        Group::make([
                            TextEntry::make('preaviso_info')
                                ->label('Preaviso')
                                ->state(fn(Liquidacion $record) => $record->preaviso_amount > 0
                                    ? "{$record->preaviso_days} días - " . Liquidacion::formatCurrency($record->preaviso_amount)
                                    : ($record->preaviso_otorgado ? 'Otorgado (no aplica pago)' : 'No aplica')),
                            TextEntry::make('indemnizacion_amount')
                                ->label('Indemnización')
                                ->money('PYG', locale: 'es_PY'),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('vacaciones_info')
                                ->label('Vacaciones Proporcionales')
                                ->state(fn(Liquidacion $record) => $record->vacaciones_amount > 0
                                    ? "{$record->vacaciones_days} días - " . Liquidacion::formatCurrency($record->vacaciones_amount)
                                    : 'Gs. 0'),
                            TextEntry::make('aguinaldo_proporcional_amount')
                                ->label('Aguinaldo Proporcional')
                                ->money('PYG', locale: 'es_PY'),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('salario_pendiente_info')
                                ->label('Salario Pendiente')
                                ->state(fn(Liquidacion $record) => $record->salario_pendiente_amount > 0
                                    ? "{$record->salario_pendiente_days} días - " . Liquidacion::formatCurrency($record->salario_pendiente_amount)
                                    : 'Gs. 0'),
                            TextEntry::make('total_haberes')
                                ->label('TOTAL HABERES')
                                ->money('PYG', locale: 'es_PY')
                                ->weight('bold')
                                ->color('success'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Descuentos')
                    ->schema([
                        Group::make([
                            TextEntry::make('ips_deduction')
                                ->label('Aporte IPS (9%)')
                                ->money('PYG', locale: 'es_PY')
                                ->color('danger'),
                            TextEntry::make('loan_deduction')
                                ->label('Préstamos Pendientes')
                                ->money('PYG', locale: 'es_PY')
                                ->color('danger'),
                        ])->columns(2),
                        TextEntry::make('total_deductions')
                            ->label('TOTAL DESCUENTOS')
                            ->money('PYG', locale: 'es_PY')
                            ->weight('bold')
                            ->color('danger'),
                    ]),

                InfolistSection::make('Neto a Pagar')
                    ->schema([
                        TextEntry::make('net_amount')
                            ->label('NETO A PAGAR')
                            ->money('PYG', locale: 'es_PY')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->icon('heroicon-o-banknotes'),
                    ]),

                InfolistSection::make('Estado y Sistema')
                    ->schema([
                        Group::make([
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'draft'      => 'gray',
                                    'calculated' => 'warning',
                                    'closed'     => 'success',
                                    default      => 'primary',
                                })
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'draft'      => 'Borrador',
                                    'calculated' => 'Calculada',
                                    'closed'     => 'Cerrada',
                                    default      => $state,
                                }),
                            TextEntry::make('pdf_path')
                                ->label('PDF')
                                ->formatStateUsing(fn($state) => $state ? 'Disponible' : 'No generado')
                                ->badge()
                                ->color(fn($state) => $state ? 'success' : 'gray'),
                        ])->columns(2),
                        Group::make([
                            TextEntry::make('calculated_at')
                                ->label('Calculada')
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('Pendiente'),
                            TextEntry::make('closed_at')
                                ->label('Cerrada')
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('Pendiente'),
                        ])->columns(2),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiquidaciones::route('/'),
            'create' => Pages\CreateLiquidacion::route('/create'),
            'view' => Pages\ViewLiquidacion::route('/{record}'),
            'edit' => Pages\EditLiquidacion::route('/{record}/edit'),
        ];
    }
}
