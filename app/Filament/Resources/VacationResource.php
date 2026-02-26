<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Employee;
use App\Models\Vacation;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\VacationBalance;
use Filament\Resources\Resource;
use App\Services\VacationService;
use Illuminate\Support\HtmlString;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\VacationResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class VacationResource extends Resource
{
    protected static ?string $model = Vacation::class;
    protected static ?string $navigationGroup = 'Empleados';
    protected static ?string $navigationLabel = 'Vacaciones';
    protected static ?string $label = 'Vacación';
    protected static ?string $pluralLabel = 'Vacaciones';
    protected static ?string $slug = 'vacaciones';
    protected static ?string $navigationIcon = 'heroicon-o-sun';
    protected static ?int $navigationSort = 2;

    /**
     * Define el formulario para crear/editar solicitudes de vacaciones.
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Empleado')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'id', function ($query) {
                                $query->where('status', 'active')->orderBy('first_name');
                            })
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('start_date', null);
                                $set('end_date', null);
                                $set('business_days', null);
                                $set('return_date', null);
                            })
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->first_name} {$record->last_name} - CI: {$record->ci}";
                            })
                            ->columnSpan(2),

                        Placeholder::make('employee_info')
                            ->label('Información de Antigüedad')
                            ->content(function (Get $get) {
                                $employeeId = $get('employee_id');
                                if (!$employeeId) {
                                    return 'Seleccione un empleado';
                                }

                                $employee = Employee::find($employeeId);
                                if (!$employee instanceof Employee) {
                                    return 'Empleado no encontrado';
                                }

                                $yearsOfService = VacationService::getYearsOfService($employee);
                                $balance = VacationBalance::where('employee_id', $employee->id)
                                    ->where('year', now()->year)
                                    ->first();

                                $antiquityColor = $yearsOfService < 1 ? 'text-danger-600' : 'text-success-600';
                                $entitledLabel = VacationBalance::getEntitledDaysLabel($yearsOfService);
                                $year = now()->year;
                                $availableDays = $balance?->available_days ?? 0;
                                $usedDays = $balance?->used_days ?? 0;
                                $pendingDays = $balance?->pending_days ?? 0;

                                return new HtmlString("
                                    <div class='space-y-1 text-sm'>
                                        <p><strong>Antigüedad:</strong> <span class='{$antiquityColor}'>{$employee->antiquity_description}</span></p>
                                        <p><strong>Derecho {$year}:</strong> {$entitledLabel}</p>
                                        <p><strong>Disponibles:</strong> {$availableDays} días | <strong>Usados:</strong> {$usedDays} días | <strong>Pendientes:</strong> {$pendingDays} días</p>
                                    </div>
                                ");
                            })
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Período de Vacaciones')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('end_date', null);
                                $set('business_days', null);
                                $set('return_date', null);
                            })
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->minDate(fn(Get $get) => $get('start_date'))
                            ->disabled(fn(Get $get) => !$get('start_date'))
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $employeeId = $get('employee_id');
                                $startDate = $get('start_date');

                                if (!$employeeId || !$startDate || !$state) {
                                    return;
                                }

                                $employee = Employee::find($employeeId);
                                if (!$employee instanceof Employee) {
                                    return;
                                }

                                $startDate = Carbon::parse($startDate);
                                $endDate = Carbon::parse($state);

                                $businessDays = VacationService::calculateBusinessDays($employee, $startDate, $endDate);
                                $returnDate = VacationService::calculateReturnDate($employee, $endDate);

                                $set('business_days', $businessDays);
                                $set('return_date', $returnDate->format('Y-m-d'));
                            })
                            ->columnSpan(1),

                        Placeholder::make('calculation_info')
                            ->label('Cálculo de Días')
                            ->content(function (Get $get) {
                                $businessDays = $get('business_days');
                                $returnDate = $get('return_date');
                                $employeeId = $get('employee_id');

                                if (!$businessDays) {
                                    return 'Seleccione las fechas para calcular';
                                }

                                $balance = $employeeId
                                    ? VacationBalance::where('employee_id', $employeeId)
                                        ->where('year', now()->year)
                                        ->first()
                                    : null;
                                $available = $balance?->available_days ?? 0;

                                $daysColor = $businessDays > $available ? 'text-danger-600' : 'text-success-600';
                                $returnFormatted = $returnDate ? \Carbon\Carbon::parse($returnDate)->format('d/m/Y') : '-';

                                $warning = '';
                                if ($businessDays > 0 && $businessDays < 6) {
                                    $warning = "<p class='text-warning-600 text-xs mt-1'>⚠️ El fraccionamiento mínimo legal es de 6 días hábiles</p>";
                                }

                                return new HtmlString("
                                    <div class='space-y-1 text-sm'>
                                        <p><strong>Días hábiles:</strong> <span class='{$daysColor}'>{$businessDays} días</span> (Disponibles: {$available})</p>
                                        <p><strong>Fecha de reintegro:</strong> {$returnFormatted}</p>
                                        {$warning}
                                    </div>
                                ");
                            })
                            ->columnSpan(2),

                        Hidden::make('business_days'),
                        Hidden::make('return_date'),
                        Hidden::make('vacation_balance_id'),
                    ])
                    ->columns(2),

                Section::make('Detalles de la Solicitud')
                    ->schema([
                        Select::make('type')
                            ->label('Tipo de Vacaciones')
                            ->options(Vacation::getTypeOptions())
                            ->default('paid')
                            ->native(false)
                            ->required()
                            ->columnSpan(1),

                        Select::make('status')
                            ->label('Estado')
                            ->options(Vacation::getStatusOptions())
                            ->default('pending')
                            ->native(false)
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),

                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Motivo de la solicitud (opcional)...')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Define la tabla para listar las solicitudes de vacaciones.
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->sortable(['first_name', 'last_name'])
                    ->searchable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->icon('heroicon-o-identification')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('Cédula copiada'),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('business_days')
                    ->label('Días Háb.')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->tooltip('Días hábiles (excluye domingos y feriados)'),

                TextColumn::make('return_date')
                    ->label('Reintegro')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => Vacation::getTypeColor($state))
                    ->formatStateUsing(fn(string $state): string => Vacation::getTypeLabel($state))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->badge()
                    ->color(fn(string $state): string => Vacation::getStatusColor($state))
                    ->icon(fn(string $state): string => Vacation::getStatusIcon($state))
                    ->formatStateUsing(fn(string $state): string => Vacation::getStatusLabel($state)),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options(Vacation::getStatusOptions())
                    ->native(false),

                SelectFilter::make('type')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->options(Vacation::getTypeOptions())
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable()
                    ->preload()
                    ->native(false),

                Filter::make('current_year')
                    ->label('Año Actual')
                    ->query(fn($query) => $query->whereYear('start_date', now()->year))
                    ->default(),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Solicitud de Vacaciones')
                    ->modalDescription(function ($record) {
                        $days = $record->business_days ?? $record->total_days;
                        $returnDate = $record->return_date?->format('d/m/Y') ?? 'No calculada';
                        return "¿Aprobar vacaciones de {$record->employee->full_name}?\n\nPeríodo: {$record->start_date->format('d/m/Y')} al {$record->end_date->format('d/m/Y')}\nDías hábiles: {$days}\nFecha de reintegro: {$returnDate}";
                    })
                    ->action(function ($record) {
                        VacationService::approve($record);

                        Notification::make()
                            ->title('Vacaciones aprobadas')
                            ->body("Las vacaciones de {$record->employee->full_name} fueron aprobadas exitosamente.")
                            ->success()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Solicitud de Vacaciones')
                    ->modalDescription(fn($record) => "¿Está seguro de rechazar las vacaciones de {$record->employee->full_name}?")
                    ->action(function ($record) {
                        VacationService::reject($record);

                        Notification::make()
                            ->title('Vacaciones rechazadas')
                            ->body("Las vacaciones de {$record->employee->full_name} fueron rechazadas.")
                            ->warning()
                            ->send();
                    }),

                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approveBulk')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Vacaciones')
                        ->modalDescription('Se aprobarán las vacaciones seleccionadas que estén en estado pendiente.')
                        ->action(function (Collection $records) {
                            $approved = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    VacationService::approve($record);
                                    $approved++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Vacaciones Aprobadas')
                                ->body("Se aprobaron {$approved} solicitudes." . ($skipped > 0 ? " Se omitieron {$skipped} que no estaban pendientes." : ''))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('rejectBulk')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar Vacaciones')
                        ->modalDescription('Se rechazarán las vacaciones seleccionadas que estén en estado pendiente.')
                        ->action(function (Collection $records) {
                            $rejected = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    VacationService::reject($record);
                                    $rejected++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->warning()
                                ->title('Vacaciones Rechazadas')
                                ->body("Se rechazaron {$rejected} solicitudes." . ($skipped > 0 ? " Se omitieron {$skipped} que no estaban pendientes." : ''))
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'created_at',
                                    'updated_at',
                                    'employee_id',
                                ])
                                ->withFilename('vacaciones_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->emptyStateHeading('No hay solicitudes de vacaciones')
            ->emptyStateDescription('Las solicitudes de vacaciones aparecerán aquí una vez que sean creadas.')
            ->emptyStateIcon('heroicon-o-sun');
    }

    /**
     * Define las páginas para el recurso de vacaciones.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVacations::route('/'),
            'create' => Pages\CreateVacation::route('/create'),
            'edit' => Pages\EditVacation::route('/{record}/edit'),
        ];
    }

    /**
     * Define la insignia de navegación para el recurso de vacaciones.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    /**
     * Define el color de la insignia de navegación para el recurso de vacaciones.
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /**
     * Define el tooltip de la insignia de navegación para el recurso de vacaciones, indicando que el número representa las "Solicitudes de vacaciones pendientes".
     *
     * @return string|null
     */
    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Solicitudes de vacaciones pendientes';
    }
}
