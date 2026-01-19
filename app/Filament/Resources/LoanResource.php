<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use App\Models\Loan;
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
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\LoanResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\LoanResource\RelationManagers\InstallmentsRelationManager;

class LoanResource extends Resource
{
    protected static ?string $model = Loan::class;
    protected static ?string $navigationLabel = 'Préstamos';
    protected static ?string $label = 'préstamo';
    protected static ?string $pluralLabel = 'préstamos';
    protected static ?string $slug = 'prestamos';
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        $settings = app(GeneralSettings::class);
        $maxLoanAmount = $settings->max_loan_amount;

        return $form
            ->schema([
                Section::make('Información del Préstamo')
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
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        TextInput::make('amount')
                            ->label('Monto Total')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue($maxLoanAmount)
                            ->prefix('Gs.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                $installments = $get('installments_count');
                                if ($state && $installments) {
                                    $set('installment_amount', round($state / $installments, 0));
                                }
                            })
                            ->helperText("Monto máximo permitido: " . number_format($maxLoanAmount, 0, ',', '.') . " Gs.")
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
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Notas adicionales...')
                            ->rows(2)
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

    public static function table(Table $table): Table
    {
        return $table
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
                            ->when(
                                $data['granted_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('granted_at', '>=', $date),
                            )
                            ->when(
                                $data['granted_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('granted_at', '<=', $date),
                            );
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
                    ->modalHeading('Activar Préstamo')
                    ->modalDescription(fn(Loan $record) => "Se generarán {$record->installments_count} cuotas de " . number_format($record->installment_amount, 0, ',', '.') . " Gs. cada una.")
                    ->form([
                        DatePicker::make('start_date')
                            ->label('Fecha de primera cuota')
                            ->default(now()->addMonth()->startOfMonth())
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->helperText('Las cuotas siguientes serán el mismo día de cada mes'),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $startDate = Carbon::parse($data['start_date']);
                        $result = $record->activate(Auth::id(), $startDate);

                        Notification::make()
                            ->success()
                            ->title('Préstamo Activado')
                            ->body($result['message'])
                            ->send();
                    }),

                Action::make('cancel')
                    ->label('Cancelar')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->visible(fn(Loan $record) => $record->isPending() || ($record->isActive() && $record->paid_installments_count === 0))
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar Préstamo')
                    ->modalDescription('¿Está seguro de que desea cancelar este préstamo?')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo de cancelación')
                            ->placeholder('Ingrese el motivo...')
                            ->rows(3),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $result = $record->cancel($data['reason'] ?? null);

                        if ($result['success']) {
                            Notification::make()
                                ->success()
                                ->title('Préstamo Cancelado')
                                ->body($result['message'])
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->action(function (Loan $record) {
                        $record->load(['employee.position.department', 'grantedBy', 'installments']);

                        $pdf = Pdf::loadView('pdf.loan', ['loan' => $record])
                            ->setPaper('a4', 'portrait');

                        $type = $record->isLoan() ? 'prestamo' : 'adelanto';

                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, "{$type}_{$record->id}_{$record->employee->ci}.pdf");
                    })
                    ->visible(fn(Loan $record) => $record->isActive() || $record->isPaid()),

                /* Action::make('mark_defaulted')
                    ->label('Marcar Incobrable')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(fn(Loan $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Incobrable')
                    ->modalDescription('Esta acción marcará el préstamo como incobrable. Las cuotas pendientes serán canceladas.')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo')
                            ->placeholder('Ingrese el motivo por el cual el préstamo es incobrable...')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (Loan $record, array $data) {
                        $result = $record->markAsDefaulted($data['reason']);

                        Notification::make()
                            ->success()
                            ->title('Préstamo Marcado como Incobrable')
                            ->body($result['message'])
                            ->send();
                    }), */
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename('préstamos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'),
                        ])
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),

                    DeleteBulkAction::make()
                        ->icon('heroicon-o-trash'),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No hay préstamos registrados')
            ->emptyStateDescription('Comienza agregando tu primer préstamo al sistema')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    public static function getRelations(): array
    {
        return [
            InstallmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLoans::route('/'),
            'create' => Pages\CreateLoan::route('/create'),
            'edit' => Pages\EditLoan::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
