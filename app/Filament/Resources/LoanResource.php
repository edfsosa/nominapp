<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LoanResource\Pages;
use App\Filament\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;
use App\Models\Employee;
use App\Models\Loan;
use App\Settings\GeneralSettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationLabel = 'Préstamos y Adelantos';
    protected static ?string $label = 'préstamo/adelanto';
    protected static ?string $pluralLabel = 'préstamos/adelantos';
    protected static ?string $slug = 'prestamos';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?int $navigationSort = 5;

    /**
     * Función para definir el formulario de creación/edición de préstamos/adelantos
     *
     * @param Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        $settings = app(GeneralSettings::class);
        $maxLoanAmount = $settings->max_loan_amount;

        return $form
            ->schema([
                Section::make('Información General')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query, Get $get) => $query
                                    ->where('status', 'active')
                                    ->when(
                                        $get('type') === 'advance',
                                        fn($q) => $q->whereHas('activeContract', fn($c) => $c->whereNotNull('salary')->where('salary', '>', 0))
                                    )
                                    ->orderBy('first_name')
                                    ->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->full_name_with_ci)
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $set('amount', null);
                                $set('installment_amount', null);

                                $max = null;
                                if ($get('type') === 'advance' && $get('employee_id')) {
                                    $employee = Employee::find($get('employee_id'));
                                    $max = $employee?->getMaxAdvanceAmount();
                                }
                                $set('max_advance_amount', $max);
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->helperText('Solo se muestran empleados activos.'),

                        Select::make('type')
                            ->label('Tipo')
                            ->options(Loan::getTypeOptions())
                            ->required()
                            ->native(false)
                            ->default('loan')
                            ->live()
                            ->afterStateUpdated(function (string $state, Set $set) {
                                if ($state === 'advance') {
                                    $set('installments_count', 1);
                                }
                                $set('employee_id', null);
                                $set('amount', null);
                                $set('installment_amount', null);
                                $set('max_advance_amount', null);
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->helperText('Los adelantos son siempre de 1 cuota y tienen un monto máximo basado en el salario del empleado.'),

                        Select::make('reason')
                            ->label('Motivo')
                            ->options([
                                'personal' => 'Personal',
                                'medical' => 'Médico',
                                'education' => 'Educación',
                                'other' => 'Otro',
                            ])
                            ->default('personal')
                            ->native(false)
                            ->placeholder('Seleccione un motivo')
                            ->required(),

                        TextInput::make('amount')
                            ->label('Monto Total')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function (Get $get) use ($maxLoanAmount) {
                                if ($get('type') === 'advance') {
                                    return $get('max_advance_amount') ?? $maxLoanAmount;
                                }
                                return $maxLoanAmount;
                            })
                            ->prefix('Gs.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $installments = $get('installments_count');
                                if ($state && $installments) {
                                    $set('installment_amount', round($state / $installments, 0));
                                }
                            })
                            ->helperText(function (Get $get) use ($maxLoanAmount) {
                                if ($get('type') === 'advance') {
                                    $max = $get('max_advance_amount');
                                    return $max
                                        ? "Máximo: " . number_format($max, 0, ',', '.') . " Gs. (50% del salario devengado en el período)"
                                        : "Seleccione un empleado para ver el monto máximo";
                                }
                                return "Máximo: " . number_format($maxLoanAmount, 0, ',', '.') . " Gs.";
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        TextInput::make('installments_count')
                            ->label('Cantidad de Cuotas')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(24)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $amount = $get('amount');
                                if ($amount && $state) {
                                    $set('installment_amount', round($amount / $state, 0));
                                }
                            })
                            ->disabled(fn(string $operation, Get $get) => $operation === 'edit' || $get('type') === 'advance')
                            ->helperText(fn(Get $get) => $get('type') === 'advance' ? 'Los adelantos son siempre de 1 cuota' : null),

                        TextInput::make('installment_amount')
                            ->label('Monto por Cuota')
                            ->numeric()
                            ->prefix('Gs.')
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Calculado automáticamente'),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Notas adicionales...')
                            ->rows(1)
                            ->columnSpanFull(),

                        Hidden::make('max_advance_amount')->dehydrated(false),
                    ])
                    ->columns(3),

                Section::make('Estado y Aprobación')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(Loan::getStatusOptions())
                            ->required()
                            ->native(false)
                            ->default('pending')
                            ->disabled(),

                        DatePicker::make('granted_at')
                            ->label('Fecha de Otorgamiento')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->disabled(),

                        Select::make('granted_by_id')
                            ->label('Otorgado por')
                            ->relationship('grantedBy', 'name')
                            ->native(false)
                            ->disabled(),

                        Placeholder::make('progress')
                            ->label('Progreso')
                            ->content(fn(?Loan $record) => $record?->progress_description ?? '-')
                            ->visible(fn(string $operation) => $operation === 'edit'),
                    ])
                    ->columns(2)
                    ->visible(fn(string $operation) => $operation === 'edit'),
            ]);
    }

    /**
     * Función para definir el infolist de visualización de préstamos/adelantos
     *
     * @param Infolist $infolist
     * @return Infolist
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Empleado')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.full_name')
                                ->label('Empleado')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('employee.ci')
                                ->label('CI')
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
                        ])->columns(3),
                    ]),

                InfolistSection::make('Datos del Préstamo / Adelanto')
                    ->schema([
                        Group::make([
                            TextEntry::make('type')
                                ->label('Tipo')
                                ->formatStateUsing(fn(string $state) => Loan::getTypeLabel($state))
                                ->color(fn(string $state) => Loan::getTypeColor($state))
                                ->icon(fn(string $state) => Loan::getTypeIcon($state))
                                ->badge(),

                            TextEntry::make('reason')
                                ->label('Motivo')
                                ->formatStateUsing(fn(?string $state) => match ($state) {
                                    'personal'  => 'Personal',
                                    'medical'   => 'Médico',
                                    'education' => 'Educación',
                                    'other'     => 'Otro',
                                    default     => $state ?? '-',
                                })
                                ->badge()
                                ->color('gray'),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->formatStateUsing(fn(string $state) => Loan::getStatusLabel($state))
                                ->color(fn(string $state) => Loan::getStatusColor($state))
                                ->icon(fn(string $state) => Loan::getStatusIcon($state))
                                ->badge(),
                        ])->columns(3),

                        Group::make([
                            TextEntry::make('amount')
                                ->label('Monto Total')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('installment_amount')
                                ->label('Monto por Cuota')
                                ->money('PYG', locale: 'es_PY'),

                            TextEntry::make('installments_count')
                                ->label('Progreso')
                                ->formatStateUsing(fn(Loan $record) => $record->progress_description),
                        ])->columns(3),

                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('Aprobación')
                    ->schema([
                        Group::make([
                            TextEntry::make('granted_at')
                                ->label('Fecha de Otorgamiento')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar')
                                ->placeholder('-'),

                            TextEntry::make('grantedBy.name')
                                ->label('Otorgado por')
                                ->icon('heroicon-o-user-circle')
                                ->placeholder('-'),
                        ])->columns(2),
                    ])
                    ->visible(fn(Loan $record) => $record->isActive() || $record->isPaid()),
            ]);
    }

    /**
     * Función para definir la tabla de visualización de préstamos/adelantos
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query
                    ->with(['employee', 'grantedBy'])
                    ->withCount(['installments as paid_count' => fn($q) => $q->where('status', 'paid')])
            )
            ->columns([
                ImageColumn::make('employee.photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn($record) => $record->employee->avatar_url)
                    ->toggleable(),

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
                    ->copyMessage('CI copiada al portapapeles'),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Loan::getTypeLabel($state))
                    ->color(fn(string $state): string => Loan::getTypeColor($state))
                    ->icon(fn(string $state): string => Loan::getTypeIcon($state))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('installments_count')
                    ->label('Cuotas')
                    ->formatStateUsing(fn(Loan $record) => $record->progress_description)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('installment_amount')
                    ->label('Monto por Cuota')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Loan::getStatusLabel($state))
                    ->color(fn(string $state): string => Loan::getStatusColor($state))
                    ->icon(fn(string $state): string => Loan::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('granted_at')
                    ->label('Otorgado')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('grantedBy.name')
                    ->label('Otorgado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Loan::getTypeOptions())
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Loan::getStatusOptions())
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->native(false),

                Filter::make('granted_at')
                    ->label('Fecha de Otorgamiento')
                    ->form([
                        DatePicker::make('granted_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('granted_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['granted_from'], fn(Builder $q, $date) => $q->whereDate('granted_at', '>=', $date))
                            ->when($data['granted_until'], fn(Builder $q, $date) => $q->whereDate('granted_at', '<=', $date));
                    }),
            ])
            ->actions([
                Action::make('activate')
                    ->label('Activar')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->visible(fn(Loan $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading(fn(Loan $record) => "Activar {$record->type_label}")
                    ->modalDescription(function (Loan $record) {
                        $amount = number_format($record->installment_amount, 0, ',', '.');
                        $payrollType = $record->employee->payroll_type_label;

                        if ($record->isAdvance()) {
                            return "Se generará 1 cuota de {$amount} Gs. que se descontará automáticamente en la nómina actual ({$payrollType}).";
                        }

                        return "Se generarán {$record->installments_count} cuotas de {$amount} Gs. cada una. La primera cuota se descontará en la próxima nómina ({$payrollType}).";
                    })
                    ->action(function (Loan $record) {
                        $result = $record->activate(Auth::id());

                        Notification::make()
                            ->title($result['success'] ? "{$record->type_label} Activado" : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Loan $record) => $record->isPending() || ($record->isActive() && $record->paid_count == 0))
                    ->requiresConfirmation()
                    ->modalHeading(fn(Loan $record) => "Cancelar {$record->type_label}")
                    ->modalDescription(fn(Loan $record) => "¿Está seguro de que desea cancelar este {$record->type_label}?")
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo de cancelación')
                            ->placeholder('Ingrese el motivo...')
                            ->rows(3),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $result = $record->cancel($data['reason'] ?? null);

                        Notification::make()
                            ->title($result['success'] ? "{$record->type_label} Cancelado" : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    }),

                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn(Loan $record) => $record->isActive() || $record->isPaid())
                    ->url(fn(Loan $record) => route('loans.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activateBulk')
                        ->label('Activar')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activar Préstamos/Adelantos')
                        ->modalDescription('Se activarán los préstamos/adelantos seleccionados que estén en estado pendiente.')
                        ->action(function (Collection $records) {
                            $activated = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->isPending()) {
                                    $result = $record->activate(Auth::id());
                                    $result['success'] ? $activated++ : $failed++;
                                }
                            }

                            $message = "Se activaron {$activated} préstamos/adelantos.";
                            if ($failed > 0) {
                                $message .= " {$failed} fallaron (posiblemente ya tienen nómina generada).";
                            }

                            Notification::make()
                                ->title('Activación Completada')
                                ->body($message)
                                ->{$failed > 0 ? 'warning' : 'success'}()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('cancelBulk')
                        ->label('Cancelar')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancelar Préstamos/Adelantos')
                        ->modalDescription('Se cancelarán los préstamos/adelantos seleccionados que estén pendientes o activos sin cuotas pagadas.')
                        ->form([
                            Textarea::make('reason')
                                ->label('Motivo de cancelación')
                                ->placeholder('Ingrese el motivo...')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $cancelled = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->isPending() || ($record->isActive() && $record->paid_count == 0)) {
                                    $result = $record->cancel($data['reason'] ?? null);
                                    $result['success'] ? $cancelled++ : $skipped++;
                                } else {
                                    $skipped++;
                                }
                            }

                            $body = "Se cancelaron {$cancelled} préstamos/adelantos.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} no pudieron cancelarse (tienen cuotas pagadas o ya están en un estado final).";
                            }

                            Notification::make()
                                ->title('Cancelación Completada')
                                ->body($body)
                                ->{$skipped > 0 ? 'warning' : 'success'}()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename('prestamos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'),
                        ])
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),

                    DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay préstamos o adelantos aún')
            ->emptyStateDescription('Comienza agregando tu primer préstamo o adelanto al sistema')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /**
     * Función para definir las relaciones del recurso
     *
     * @return array
     */
    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    /**
     * Función para definir las páginas del recurso
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'view'   => Pages\ViewLoan::route('/{record}'),
            'edit'   => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    /**
     * Función para definir el badge de navegación del recurso
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    /**
     * Función para definir el color del badge de navegación del recurso
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
