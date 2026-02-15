<?php

namespace App\Filament\Resources;

use App\Models\Contract;
use App\Models\Position;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use App\Settings\GeneralSettings;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\ContractResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

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
                    ->toggleable()
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

                TextColumn::make('salary')
                    ->label('Salario')
                    ->formatStateUsing(fn(Contract $record) => $record->formatted_salary)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('work_modality')
                    ->label('Modalidad')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Contract::getWorkModalityLabel($state))
                    ->color(fn(string $state): string => Contract::getWorkModalityColor($state))
                    ->icon(fn(string $state): string => Contract::getWorkModalityIcon($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('Indefinido')
                    ->description(fn(Contract $record) => $record->expiration_description)
                    ->color(function (Contract $record) use ($alertDays) {
                        if (!$record->end_date || $record->status !== 'active') {
                            return null;
                        }
                        if ($record->isExpired()) {
                            return 'danger';
                        }
                        if ($record->isExpiringSoon($alertDays)) {
                            return 'warning';
                        }
                        return null;
                    }),

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
            ->defaultSort('created_at', 'desc')
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
                // --- Botones directos (flujo diario) ---

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
                        $newContract = $record->renew($data);
                        $newContract->update(['created_by_id' => Auth::id()]);

                        $newContract->syncToEmployee();

                        $typeMsg = $newContract->type === 'indefinido' && $record->type !== 'indefinido'
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
                    ->action(function (Contract $record, array $data) {
                        if ($record->document_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($record->document_path)) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($record->document_path);
                        }

                        $record->update(['document_path' => $data['document_path']]);

                        Notification::make()
                            ->title('Documento subido')
                            ->body('El contrato firmado se ha guardado correctamente.')
                            ->success()
                            ->send();
                    }),

                Action::make('download_signed')
                    ->label('Firmado')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn(Contract $record) => (bool) $record->document_path)
                    ->action(fn(Contract $record) => response()->download(
                        \Illuminate\Support\Facades\Storage::disk('public')->path($record->document_path),
                        "contrato_firmado_{$record->employee->ci}_{$record->start_date->format('Y_m_d')}.pdf"
                    )),

                // --- Menú dropdown (acciones de gestión) ---
                \Filament\Tables\Actions\ActionGroup::make([
                    ViewAction::make(),

                    EditAction::make()
                        ->color('primary')
                        ->visible(fn(Contract $record) => $record->status === 'active'),

                    Action::make('terminate')
                        ->label('Terminar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn(Contract $record) => $record->status === 'active')
                        ->requiresConfirmation()
                        ->modalHeading('Terminar Contrato')
                        ->modalDescription(fn(Contract $record) => "¿Está seguro de que desea terminar el contrato de {$record->employee->full_name}?")
                        ->form([
                            Textarea::make('termination_notes')
                                ->label('Motivo de terminación')
                                ->placeholder('Ingrese el motivo...')
                                ->rows(3),
                        ])
                        ->action(function (Contract $record, array $data) {
                            $record->update([
                                'status' => 'terminated',
                                'notes'  => $record->notes
                                    ? $record->notes . "\n\nTerminación: " . ($data['termination_notes'] ?? 'Sin motivo especificado')
                                    : "Terminación: " . ($data['termination_notes'] ?? 'Sin motivo especificado'),
                            ]);

                            Notification::make()
                                ->title('Contrato Terminado')
                                ->body("El contrato de {$record->employee->full_name} ha sido terminado. Puede crear una liquidación desde el módulo correspondiente.")
                                ->warning()
                                ->persistent()
                                ->actions([
                                    \Filament\Notifications\Actions\Action::make('create_liquidacion')
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
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename('contratos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'),
                        ])
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),

                    DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),
                ]),
            ])
            ->striped()
            ->emptyStateHeading('No hay contratos registrados')
            ->emptyStateDescription('Comienza agregando el primer contrato al sistema')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
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
