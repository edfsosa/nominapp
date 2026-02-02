<?php

namespace App\Filament\Resources;

use App\Models\Loan;
use App\Models\Employee;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Resources\Resource;
use App\Settings\GeneralSettings;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\LoanResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;

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
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query, Get $get) => $query
                                    ->where('status', 'active')
                                    ->when(
                                        $get('type') === 'advance',
                                        fn($q) => $q->whereNotNull('base_salary')->where('base_salary', '>', 0)
                                    )
                                    ->orderBy('first_name')
                                    ->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->full_name_with_ci)
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('amount', null);
                                $set('installment_amount', null);
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->columnSpan(2),

                        TextInput::make('amount')
                            ->label('Monto Total')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(function (Get $get) use ($maxLoanAmount) {
                                if ($get('type') === 'advance' && $get('employee_id')) {
                                    $employee = Employee::find($get('employee_id'));
                                    return $employee?->base_salary ? (float) ($employee->base_salary / 2) : $maxLoanAmount;
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
                                if ($get('type') === 'advance' && $get('employee_id')) {
                                    $employee = Employee::find($get('employee_id'));
                                    if ($employee?->base_salary) {
                                        $max = $employee->base_salary / 2;
                                        return "Máximo: " . number_format($max, 0, ',', '.') . " Gs. (50% del salario)";
                                    }
                                }
                                return $get('type') === 'advance'
                                    ? "Seleccione un empleado para ver el monto máximo"
                                    : "Máximo: " . number_format($maxLoanAmount, 0, ',', '.') . " Gs.";
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
                    ])
                    ->columns(3),

                Section::make('Detalles')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ingrese el motivo del préstamo o adelanto...')
                            ->rows(1)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Notas adicionales...')
                            ->rows(1)
                            ->columnSpanFull(),
                    ]),

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
     * Función para definir la tabla de visualización de préstamos/adelantos
     *
     * @param Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee', 'grantedBy']))
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
                    ->formatStateUsing(fn(string $state) => Loan::getTypeLabel($state))
                    ->color(fn(string $state): string => Loan::getTypeColor($state))
                    ->icon(fn(string $state): string => Loan::getTypeIcon($state))
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable(),

                TextColumn::make('installments_count')
                    ->label('Cuotas')
                    ->formatStateUsing(fn(Loan $record) => $record->progress_description)
                    ->sortable(),

                TextColumn::make('installment_amount')
                    ->label('Cuota')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

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
                    ->toggleable(),

                TextColumn::make('grantedBy.name')
                    ->label('Otorgado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
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
                    ->preload(false)
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
                EditAction::make(),

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
                    ->visible(fn(Loan $record) => $record->isPending() || ($record->isActive() && $record->paid_installments_count === 0))
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
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->visible(fn(Loan $record) => $record->isActive() || $record->isPaid())
                    ->action(function (Loan $record) {
                        $record->load(['employee.position.department', 'grantedBy', 'installments']);

                        $pdf = Pdf::loadView('pdf.loan', ['loan' => $record])
                            ->setPaper('a4', 'portrait');

                        $type = $record->isLoan() ? 'prestamo' : 'adelanto';

                        return response()->streamDownload(
                            fn() => print($pdf->output()),
                            "{$type}_{$record->id}_{$record->employee->ci}.pdf"
                        );
                    }),
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
                                ->success()
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

                            foreach ($records as $record) {
                                if ($record->isPending() || ($record->isActive() && $record->paid_installments_count === 0)) {
                                    $result = $record->cancel($data['reason'] ?? null);
                                    if ($result['success']) $cancelled++;
                                }
                            }

                            Notification::make()
                                ->title('Cancelación Completada')
                                ->body("Se cancelaron {$cancelled} préstamos/adelantos.")
                                ->success()
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
            ->striped()
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
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
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
