<?php

namespace App\Filament\Resources;

use App\Models\Contract;
use App\Models\Employee;
use App\Models\Position;
use App\Services\ContractService;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Settings\GeneralSettings;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use App\Filament\Resources\ContractResource\Pages;
use Illuminate\Database\Eloquent\Model;

class ContractResource extends Resource
{
    // Configuración general del recurso
    protected static ?string $model = Contract::class;
    protected static ?string $navigationLabel = 'Contratos';
    protected static ?string $label = 'contrato';
    protected static ?string $pluralLabel = 'contratos';
    protected static ?string $slug = 'contratos';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Empleados';
    protected static ?int $navigationSort = 2;

    /**
     * Definición del formulario para crear/editar contratos
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Contrato')
                    ->description('Datos generales del contrato laboral')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->where('status', 'active')
                                    ->orderBy('first_name')
                                    ->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->full_name_with_ci)
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->columnSpan(2),

                        Select::make('type')
                            ->label('Tipo de Contrato')
                            ->options(Contract::getTypeOptions())
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) {
                                if ($state === 'indefinido') {
                                    $set('end_date', null);
                                }
                                $set('trial_days', 30);
                            }),
                    ])
                    ->columns(3),

                Section::make('Período del Contrato')
                    ->description('Fechas y duración del contrato')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->default(now())
                            ->closeOnDateSelection(),

                        DatePicker::make('end_date')
                            ->label('Fecha de Finalización')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->visible(fn(Get $get) => Contract::requiresEndDate($get('type') ?? ''))
                            ->required(fn(Get $get) => Contract::requiresEndDate($get('type') ?? ''))
                            ->after('start_date')
                            ->maxDate(function (Get $get) {
                                $startDate = $get('start_date');
                                $type = $get('type');
                                // Art. 53 CLT: Plazo fijo máximo 1 año
                                if ($startDate && in_array($type, ['plazo_fijo', 'aprendizaje'])) {
                                    return \Carbon\Carbon::parse($startDate)->addYear();
                                }
                                return null;
                            })
                            ->helperText(function (Get $get) {
                                $type = $get('type');
                                if (in_array($type, ['plazo_fijo', 'aprendizaje'])) {
                                    return 'Art. 53 CLT: Máximo 1 año para contratos a plazo determinado';
                                }
                                return null;
                            }),

                        TextInput::make('trial_days')
                            ->label('Días de Prueba')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(30)
                            ->default(30)
                            ->suffix('días')
                            ->helperText('Art. 58 CLT: Período de prueba hasta 30 días'),
                    ])
                    ->columns(3),

                Section::make('Condiciones Laborales')
                    ->description('Salario, cargo y modalidad de trabajo')
                    ->icon('heroicon-o-briefcase')
                    ->schema([
                        Select::make('salary_type')
                            ->label('Tipo de Remuneración')
                            ->options(Contract::getSalaryTypeOptions())
                            ->native(false)
                            ->default('mensual')
                            ->required()
                            ->live()
                            ->helperText('Art. 231 CLT: Forma de remuneración del trabajador'),

                        Select::make('payroll_type')
                            ->label('Tipo de Nómina')
                            ->options(\App\Models\Employee::getPayrollTypeOptions())
                            ->native(false)
                            ->default('monthly')
                            ->required()
                            ->helperText('Define la frecuencia de pago del empleado'),

                        TextInput::make('salary')
                            ->label(fn(Get $get) => $get('salary_type') === 'jornal' ? 'Jornal Diario' : 'Salario Mensual')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('Gs.')
                            ->suffix(fn(Get $get) => $get('salary_type') === 'jornal' ? '/día' : '/mes')
                            ->placeholder('0'),

                        Select::make('position_id')
                            ->label('Cargo')
                            ->options(Position::getOptionsWithDepartment())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if ($state) {
                                    $position = Position::find($state);
                                    if ($position) {
                                        $set('department_id', $position->department_id);
                                    }
                                }
                            }),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        Select::make('work_modality')
                            ->label('Modalidad de Trabajo')
                            ->options(Contract::getWorkModalityOptions())
                            ->native(false)
                            ->default('presencial')
                            ->required(),

                        Select::make('payment_method')
                            ->label('Método de Pago')
                            ->options(\App\Models\Employee::getPaymentMethodOptions())
                            ->native(false)
                            ->default('debit')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Documento Firmado')
                    ->description('Documento escaneado del contrato firmado')
                    ->icon('heroicon-o-paper-clip')
                    ->schema([
                        FileUpload::make('document_path')
                            ->label('Contrato Firmado (PDF)')
                            ->disk('public')
                            ->directory('contracts')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10 MB
                            ->downloadable()
                            ->previewable()
                            ->openable()
                            ->helperText('Suba el documento escaneado del contrato firmado. Solo PDF, máximo 10 MB.')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(string $operation) => $operation === 'edit'),

                Section::make('Notas')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Notas adicionales sobre el contrato...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Estado')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(Contract::getStatusOptions())
                            ->native(false)
                            ->default('active')
                            ->disabled(),

                        Placeholder::make('created_by')
                            ->label('Creado por')
                            ->content(fn(?Contract $record) => $record?->createdBy?->name ?? '-')
                            ->visible(fn(string $operation) => $operation === 'edit'),

                        Placeholder::make('duration')
                            ->label('Duración')
                            ->content(fn(?Contract $record) => $record?->duration_description ?? '-')
                            ->visible(fn(string $operation) => $operation === 'edit'),

                        Placeholder::make('expiration')
                            ->label('Vencimiento')
                            ->content(fn(?Contract $record) => $record?->expiration_description ?? '-')
                            ->visible(fn(string $operation, ?Contract $record = null) => $operation === 'edit' && $record?->end_date),

                        Placeholder::make('trial_status')
                            ->label('Período de Prueba')
                            ->content(function (?Contract $record) {
                                if (!$record || !$record->trial_days) {
                                    return '-';
                                }
                                if ($record->isInTrialPeriod()) {
                                    // Mostrar días restantes, formatear en entero para evitar decimales
                                    $daysLeft = (int) $record->trial_days_left;
                                    return "{$daysLeft} días restantes";
                                }
                                return 'Período de prueba finalizado';
                            })
                            ->visible(fn(string $operation) => $operation === 'edit'),
                    ])
                    ->columns(2)
                    ->visible(fn(string $operation) => $operation === 'edit'),
            ]);
    }

    /**
     * Define el infolist para mostrar el detalle de un contrato.
     *
     * @param  Infolist $infolist
     * @return Infolist
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfoSection::make('Contrato')
                ->description('Datos generales del contrato laboral')
                ->icon('heroicon-o-document-text')
                ->schema([
                    TextEntry::make('employee.full_name')
                        ->label('Empleado')
                        ->weight('bold')
                        ->columnSpan(2),

                    TextEntry::make('type')
                        ->label('Tipo')
                        ->badge()
                        ->formatStateUsing(fn($state) => Contract::getTypeLabel($state))
                        ->color(fn($state) => Contract::getTypeColor($state))
                        ->icon(fn($state) => Contract::getTypeIcon($state)),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->badge()
                        ->formatStateUsing(fn($state) => Contract::getStatusLabel($state))
                        ->color(fn($state) => Contract::getStatusColor($state))
                        ->icon(fn($state) => Contract::getStatusIcon($state)),
                ])
                ->columns(4),

            InfoSection::make('Período')
                ->description('Fechas y duración del contrato')
                ->icon('heroicon-o-calendar')
                ->schema([
                    TextEntry::make('start_date')
                        ->label('Fecha de Inicio')
                        ->date('d/m/Y'),

                    TextEntry::make('end_date')
                        ->label('Fecha de Finalización')
                        ->date('d/m/Y')
                        ->placeholder('Indefinido'),

                    TextEntry::make('trial_days')
                        ->label('Días de Prueba')
                        ->suffix(' días'),

                    TextEntry::make('duration_description')
                        ->label('Duración'),

                    TextEntry::make('expiration_description')
                        ->label('Vencimiento')
                        ->placeholder('—'),
                ])
                ->columns(3),

            InfoSection::make('Condiciones Laborales')
                ->description('Salario, cargo y modalidad de trabajo')
                ->icon('heroicon-o-briefcase')
                ->schema([
                    TextEntry::make('formatted_salary')
                        ->label('Salario'),

                    TextEntry::make('salary_type')
                        ->label('Tipo de Remuneración')
                        ->badge()
                        ->formatStateUsing(fn($state) => Contract::getSalaryTypeLabel($state))
                        ->color(fn($state) => Contract::getSalaryTypeColor($state))
                        ->icon(fn($state) => Contract::getSalaryTypeIcon($state)),

                    TextEntry::make('work_modality')
                        ->label('Modalidad')
                        ->badge()
                        ->formatStateUsing(fn($state) => Contract::getWorkModalityLabel($state))
                        ->color(fn($state) => Contract::getWorkModalityColor($state))
                        ->icon(fn($state) => Contract::getWorkModalityIcon($state)),

                    TextEntry::make('position.name')
                        ->label('Cargo'),

                    TextEntry::make('department.name')
                        ->label('Departamento'),

                    TextEntry::make('payroll_type')
                        ->label('Tipo de Nómina')
                        ->formatStateUsing(fn($state) => Employee::getPayrollTypeOptions()[$state] ?? $state),

                    TextEntry::make('payment_method')
                        ->label('Método de Pago')
                        ->formatStateUsing(fn($state) => Employee::getPaymentMethodOptions()[$state] ?? $state),
                ])
                ->columns(3),

            InfoSection::make('Notas')
                ->schema([
                    TextEntry::make('notes')
                        ->label('')
                        ->placeholder('Sin notas')
                        ->columnSpanFull(),
                ])
                ->visible(fn(Contract $record) => !empty($record->notes)),

            InfoSection::make('Registro')
                ->icon('heroicon-o-information-circle')
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('createdBy.name')
                        ->label('Creado por'),

                    TextEntry::make('created_at')
                        ->label('Creado')
                        ->dateTime('d/m/Y H:i'),

                    TextEntry::make('updated_at')
                        ->label('Actualizado')
                        ->dateTime('d/m/Y H:i'),
                ])
                ->columns(3),
        ]);
    }

    /**
     * Definición de la tabla para listar contratos
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        $settings = app(GeneralSettings::class);
        $alertDays = $settings->contract_alert_days;

        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee', 'position', 'department', 'createdBy']))
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('Cédula copiada'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Contract::getTypeLabel($state))
                    ->color(fn(string $state): string => Contract::getTypeColor($state))
                    ->icon(fn(string $state): string => Contract::getTypeIcon($state))
                    ->sortable(),

                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->wrap(),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('work_modality')
                    ->label('Modalidad')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Contract::getWorkModalityLabel($state))
                    ->color(fn(string $state): string => Contract::getWorkModalityColor($state))
                    ->icon(fn(string $state): string => Contract::getWorkModalityIcon($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Contract::getStatusLabel($state))
                    ->color(fn(string $state): string => Contract::getStatusColor($state))
                    ->icon(fn(string $state): string => Contract::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de Contrato')
                    ->options(Contract::getTypeOptions())
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Contract::getStatusOptions())
                    ->native(false),

                SelectFilter::make('salary_type')
                    ->label('Tipo de Remuneración')
                    ->options(Contract::getSalaryTypeOptions())
                    ->native(false),

                SelectFilter::make('work_modality')
                    ->label('Modalidad')
                    ->options(Contract::getWorkModalityOptions())
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->preload(false)
                    ->native(false),

                Filter::make('expiring_soon')
                    ->label('Por vencer')
                    ->query(fn(Builder $query) => $query->expiringSoon($alertDays))
                    ->toggle(),

                Filter::make('expired')
                    ->label('Vencidos')
                    ->query(fn(Builder $query) => $query->expired())
                    ->toggle(),

                Filter::make('dates')
                    ->label('Fecha de Inicio')
                    ->form([
                        DatePicker::make('start_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('start_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['start_from'], fn(Builder $q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['start_until'], fn(Builder $q, $date) => $q->whereDate('start_date', '<=', $date));
                    }),
            ])
            ->actions([
                // --- Menú dropdown (acciones de gestión) ---
                ActionGroup::make([
                    Action::make('renew')
                        ->label('Renovar')
                        ->icon('heroicon-o-arrow-path')
                        ->color('success')
                        ->visible(fn(Contract $record) => $record->status === 'active' && $record->type !== 'indefinido')
                        ->requiresConfirmation()
                        ->modalHeading('Renovar Contrato')
                        ->modalDescription(function (Contract $record) {
                            $msg = "Se creará un nuevo contrato para {$record->employee->full_name} y el contrato actual pasará a estado 'Renovado'.";
                            if ($record->wouldBecomeIndefiniteOnRenewal()) {
                                $msg .= "\n\n⚠ Art. 53 CLT: Este contrato a plazo fijo ya fue renovado anteriormente. Al renovar nuevamente, el nuevo contrato será automáticamente de tipo INDEFINIDO.";
                            }
                            return $msg;
                        })
                        ->modalSubmitActionLabel('Sí, renovar')
                        ->form([
                            DatePicker::make('start_date')
                                ->label('Fecha de Inicio')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->required()
                                ->default(fn(Contract $record) => $record->end_date ?? now())
                                ->closeOnDateSelection(),

                            DatePicker::make('end_date')
                                ->label('Fecha de Finalización')
                                ->native(false)
                                ->displayFormat('d/m/Y')
                                ->closeOnDateSelection()
                                ->visible(fn(Contract $record) => !$record->wouldBecomeIndefiniteOnRenewal())
                                ->required(fn(Contract $record) => !$record->wouldBecomeIndefiniteOnRenewal())
                                ->helperText(fn(Contract $record) => $record->type === 'plazo_fijo' ? 'Art. 53 CLT: Máximo 1 año' : null),

                            TextInput::make('salary')
                                ->label(fn(Contract $record) => $record->salary_type === 'jornal' ? 'Jornal Diario' : 'Salario Mensual')
                                ->numeric()
                                ->required()
                                ->prefix('Gs.')
                                ->suffix(fn(Contract $record) => $record->salary_type === 'jornal' ? '/día' : '/mes')
                                ->default(fn(Contract $record) => $record->salary),

                            Textarea::make('notes')
                                ->label('Notas')
                                ->placeholder('Notas sobre la renovación...')
                                ->rows(2),
                        ])
                        ->action(function (Contract $record, array $data) {
                            $oldType = $record->type;
                            $newContract = ContractService::renew($record, $data);

                            $typeMsg = $newContract->type === 'indefinido' && $oldType !== 'indefinido'
                                ? ' (convertido a INDEFINIDO por Art. 53 CLT)'
                                : '';

                            Notification::make()
                                ->title('Contrato Renovado')
                                ->body("Se creó un nuevo contrato{$typeMsg} para {$record->employee->full_name}.")
                                ->success()
                                ->send();
                        }),

                    Action::make('generate_pdf')
                        ->label('Generar PDF')
                        ->icon('heroicon-o-printer')
                        ->color('info')
                        ->url(fn(Contract $record) => route('contracts.pdf', $record))
                        ->openUrlInNewTab(),

                    Action::make('upload_signed')
                        ->label(fn(Contract $record) => $record->document_path ? 'Reemplazar Firmado' : 'Subir Firmado')
                        ->icon(fn(Contract $record) => $record->document_path ? 'heroicon-o-arrow-path' : 'heroicon-o-arrow-up-tray')
                        ->color(fn(Contract $record) => $record->document_path ? 'warning' : 'success')
                        ->visible(fn(Contract $record) => $record->status === 'active')
                        ->form([
                            FileUpload::make('document_path')
                                ->label('Contrato Firmado (PDF)')
                                ->disk('public')
                                ->directory('contracts')
                                ->acceptedFileTypes(['application/pdf'])
                                ->maxSize(10240)
                                ->required()
                                ->helperText('Suba el documento escaneado del contrato firmado. Solo PDF, máximo 10 MB.'),
                        ])
                        ->modalHeading(fn(Contract $record) => $record->document_path ? 'Reemplazar Documento Firmado' : 'Subir Contrato Firmado')
                        ->modalDescription(fn(Contract $record) => $record->document_path
                            ? 'El documento actual será reemplazado por el nuevo archivo.'
                            : 'Suba el contrato escaneado con las firmas correspondientes.')
                        ->modalSubmitActionLabel('Subir documento')
                        ->action(function (Contract $record, array $data) {
                            if ($record->document_path && Storage::disk('public')->exists($record->document_path)) {
                                Storage::disk('public')->delete($record->document_path);
                            }

                            $record->update(['document_path' => $data['document_path']]);

                            Notification::make()
                                ->title('Documento subido')
                                ->body('El contrato firmado se ha guardado correctamente.')
                                ->success()
                                ->send();
                        }),

                    Action::make('download_signed')
                        ->label('Descargar Firmado')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('gray')
                        ->visible(fn(Contract $record) => (bool) $record->document_path)
                        ->action(fn(Contract $record) => response()->download(
                            Storage::disk('public')->path($record->document_path),
                            "contrato_firmado_{$record->employee->ci}_{$record->start_date->format('Y_m_d')}.pdf"
                        )),

                    Action::make('terminate')
                        ->label('Terminar Contrato')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(Contract $record) => $record->status === 'active')
                        ->requiresConfirmation()
                        ->modalHeading('Terminar Contrato')
                        ->modalDescription(fn(Contract $record) => "¿Está seguro de que desea terminar el contrato de {$record->employee->full_name}?")
                        ->modalSubmitActionLabel('Sí, terminar')
                        ->form([
                            Textarea::make('termination_notes')
                                ->label('Motivo de terminación')
                                ->placeholder('Ingrese el motivo...')
                                ->rows(3),
                        ])
                        ->action(function (Contract $record, array $data) {
                            ContractService::terminate($record, $data['termination_notes'] ?? null);

                            Notification::make()
                                ->title('Contrato Terminado')
                                ->body("El contrato de {$record->employee->full_name} ha sido terminado. Puede crear una liquidación desde el módulo correspondiente.")
                                ->warning()
                                ->persistent()
                                ->actions([
                                    NotificationAction::make('create_liquidacion')
                                        ->label('Crear Liquidación')
                                        ->url(route('filament.admin.resources.liquidaciones.create', [
                                            'employee_id' => $record->employee_id,
                                        ]))
                                        ->button(),
                                ])
                                ->send();
                        }),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->tooltip('Más acciones'),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay contratos registrados')
            ->emptyStateDescription('Comienza agregando el primer contrato al sistema')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    /**
     * Definición de las páginas del recurso.
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'view' => Pages\ViewContract::route('/{record}'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }

    /**
     * Muestra un badge en el menú de navegación con la cantidad de contratos que están por vencer según el período configurado en ajustes generales.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        $settings = app(GeneralSettings::class);
        $count = static::getModel()::expiringSoon($settings->contract_alert_days)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Define el color del badge en el menú de navegación para contratos por vencer.
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Define el tooltip del badge en el menú de navegación para contratos por vencer, indicando que el número representa los "Contratos por vencer".
     *
     * @return string|null
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Contratos por vencer';
    }
}
