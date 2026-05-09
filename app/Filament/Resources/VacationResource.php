<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Vacation;
use App\Settings\PayrollSettings;
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
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section as InfolistSection;
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
                            }),

                        Select::make('type')
                            ->label('Tipo de Vacaciones')
                            ->options(Vacation::getTypeOptions())
                            ->default('paid')
                            ->native(false)
                            ->required(),

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
                            ->columnSpanFull(),
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
                                $startDate  = $get('start_date');
                                $endDate    = $get('end_date');
                                $employeeId = $get('employee_id');

                                if (!$startDate || !$endDate) {
                                    return 'Seleccione las fechas para calcular';
                                }

                                $businessDays   = $get('business_days') ?? 0;
                                $returnDate     = $get('return_date');
                                $start          = Carbon::parse($startDate);
                                $end            = Carbon::parse($endDate);
                                $workingDays    = app(PayrollSettings::class)->vacation_business_days;

                                $balance = $employeeId
                                    ? VacationBalance::where('employee_id', $employeeId)
                                    ->where('year', $start->year)
                                    ->first()
                                    : null;
                                $available = $balance?->available_days ?? 0;

                                // Feriados en el período
                                $holidays = Holiday::whereBetween('date', [$start, $end])
                                    ->orderBy('date')
                                    ->get();

                                // Días no hábiles por configuración (ej: domingos) que no son feriados
                                $holidayDates   = $holidays->pluck('date')->map(fn($d) => $d->format('Y-m-d'))->toArray();
                                $nonWorkingDays = 0;
                                foreach (CarbonPeriod::create($start, $end) as $date) {
                                    if (!in_array($date->dayOfWeekIso, $workingDays) && !in_array($date->format('Y-m-d'), $holidayDates)) {
                                        $nonWorkingDays++;
                                    }
                                }

                                $daysColor      = $businessDays > $available ? 'text-danger-600' : 'text-success-600';
                                $returnFormatted = $returnDate ? Carbon::parse($returnDate)->format('d/m/Y') : '-';

                                $holidaysHtml = '';
                                if ($holidays->isNotEmpty()) {
                                    $list  = $holidays->map(fn($h) => $h->date->format('d/m') . ' – ' . e($h->name))->join(', ');
                                    $label = $holidays->count() === 1 ? '1 feriado' : $holidays->count() . ' feriados';
                                    $holidaysHtml = "<p class='text-blue-600 text-xs mt-1'>📅 {$label}: {$list}</p>";
                                }

                                $nonWorkingHtml = '';
                                if ($nonWorkingDays > 0) {
                                    $label = $nonWorkingDays === 1 ? '1 día no hábil' : "{$nonWorkingDays} días no hábiles";
                                    $nonWorkingHtml = "<p class='text-gray-500 text-xs mt-1'>🚫 {$label} (domingos)</p>";
                                }

                                $warning = '';
                                if ($businessDays > 0 && $businessDays < 6) {
                                    $warning = "<p class='text-warning-600 text-xs mt-1'>⚠️ El fraccionamiento mínimo legal es de 6 días hábiles</p>";
                                }

                                return new HtmlString("
                                    <div class='space-y-1 text-sm'>
                                        <p><strong>Días hábiles:</strong> <span class='{$daysColor}'>{$businessDays} días</span> (Disponibles: {$available})</p>
                                        <p><strong>Fecha de reintegro:</strong> {$returnFormatted}</p>
                                        {$holidaysHtml}
                                        {$nonWorkingHtml}
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

                Action::make('unapprove')
                    ->label('Desaprobar')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn($record) => $record->status === 'approved' && $record->start_date->isFuture())
                    ->requiresConfirmation()
                    ->modalHeading('Desaprobar Solicitud de Vacaciones')
                    ->modalDescription(fn($record) => "¿Revertir la aprobación de las vacaciones de {$record->employee->full_name}? La solicitud volverá a estado pendiente.")
                    ->modalSubmitActionLabel('Sí, desaprobar')
                    ->action(function ($record) {
                        VacationService::unapprove($record);

                        Notification::make()
                            ->title('Vacaciones desaprobadas')
                            ->body("La solicitud de {$record->employee->full_name} volvió a estado pendiente.")
                            ->warning()
                            ->send();
                    }),
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
     * Define el infolist para la vista de detalle de una vacación.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Empleado')
                    ->icon('heroicon-o-user')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.full_name')
                                ->label('Nombre')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('employee.ci')
                                ->label('Cédula de Identidad')
                                ->icon('heroicon-o-identification')
                                ->badge()
                                ->color('gray')
                                ->copyable(),

                            TextEntry::make('employee.activeContract.position.name')
                                ->label('Cargo')
                                ->icon('heroicon-o-briefcase')
                                ->badge()
                                ->color('info')
                                ->placeholder('-'),

                            TextEntry::make('employee.branch.name')
                                ->label('Sucursal')
                                ->icon('heroicon-o-building-storefront')
                                ->placeholder('-'),
                        ])->columns(4),
                    ]),

                InfolistSection::make('Período de Vacaciones')
                    ->icon('heroicon-o-sun')
                    ->schema([
                        Group::make([
                            TextEntry::make('start_date')
                                ->label('Fecha de Inicio')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('end_date')
                                ->label('Fecha de Fin')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('return_date')
                                ->label('Fecha de Reintegro')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-arrow-uturn-right')
                                ->placeholder('-'),

                            TextEntry::make('business_days')
                                ->label('Días Hábiles')
                                ->icon('heroicon-o-calculator')
                                ->badge()
                                ->color('info')
                                ->suffix(' días'),
                        ])->columns(4),

                        Group::make([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->formatStateUsing(fn(string $state) => Vacation::getTypeLabel($state))
                                ->color(fn(string $state) => Vacation::getTypeColor($state))
                                ->badge(),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->formatStateUsing(fn(string $state) => Vacation::getStatusLabel($state))
                                ->color(fn(string $state) => Vacation::getStatusColor($state))
                                ->icon(fn(string $state) => Vacation::getStatusIcon($state))
                                ->badge(),

                            TextEntry::make('created_at')
                                ->label('Solicitado')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-clock'),
                        ])->columns(3),
                    ]),

                InfolistSection::make('Balance de Vacaciones')
                    ->icon('heroicon-o-chart-bar')
                    ->schema([
                        Group::make([
                            TextEntry::make('vacationBalance.entitled_days')
                                ->label('Días con Derecho')
                                ->suffix(' días')
                                ->icon('heroicon-o-gift'),

                            TextEntry::make('vacationBalance.used_days')
                                ->label('Días Usados')
                                ->suffix(' días')
                                ->icon('heroicon-o-check-circle')
                                ->color('success'),

                            TextEntry::make('vacationBalance.pending_days')
                                ->label('Días Pendientes')
                                ->suffix(' días')
                                ->icon('heroicon-o-clock')
                                ->color('warning'),

                            TextEntry::make('vacationBalance.available_days')
                                ->label('Días Disponibles')
                                ->suffix(' días')
                                ->icon('heroicon-o-star')
                                ->color('info'),
                        ])->columns(4),
                    ])
                    ->visible(fn(Vacation $record) => $record->vacationBalance !== null),

                InfolistSection::make('Remuneración Vacacional')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Group::make([
                            TextEntry::make('payment_amount')
                                ->label('Monto a Cobrar')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes')
                                ->placeholder('No calculado'),

                            TextEntry::make('payment_status')
                                ->label('Estado de Pago')
                                ->formatStateUsing(fn(?string $state) => match ($state) {
                                    'paid'   => 'Pagado',
                                    'unpaid' => 'Pendiente',
                                    default  => '-',
                                })
                                ->color(fn(?string $state) => match ($state) {
                                    'paid'   => 'success',
                                    'unpaid' => 'warning',
                                    default  => 'gray',
                                })
                                ->badge(),

                            TextEntry::make('paid_at')
                                ->label('Fecha de Pago')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-calendar-days')
                                ->placeholder('No registrado'),
                        ])->columns(3),
                    ])
                    ->visible(fn(Vacation $record) => $record->type === 'paid'),
            ]);
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
            'view' => Pages\ViewVacation::route('/{record}'),
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
