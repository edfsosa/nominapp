<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Advance;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/** Gestiona los adelantos de salario del empleado desde su vista de detalle. */
class AdvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'advances';

    protected static ?string $title = 'Adelantos';

    protected static ?string $modelLabel = 'adelanto';

    protected static ?string $pluralModelLabel = 'adelantos';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario inline para crear adelantos desde la ficha del empleado.
     */
    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([]);
    }

    /**
     * Define la tabla de adelantos del empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Advance $record) => route('filament.admin.resources.adelantos.view', $record))
            ->columns([
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Advance::getStatusLabel($state))
                    ->color(fn (string $state) => Advance::getStatusColor($state))
                    ->icon(fn (string $state) => Advance::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Advance::getPaymentMethodLabel($state))
                    ->color(fn (string $state) => Advance::getPaymentMethodColor($state))
                    ->icon(fn (string $state) => Advance::getPaymentMethodIcon($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('Aprobado')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Advance::getStatusOptions())
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Adelanto')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Registrar Adelanto')
                    ->modalSubmitActionLabel('Crear adelanto')
                    ->form([
                        TextInput::make('amount')
                            ->label('Monto')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('Gs.')
                            ->helperText(function () {
                                $employee = $this->getOwnerRecord();
                                $max = $employee->getMaxAdvanceAmount();
                                $percent = (int) app(PayrollSettings::class)->advance_max_percent;

                                return $max
                                    ? 'Máximo: '.number_format($max, 0, ',', '.').' Gs. ('.$percent.'% del salario)'
                                    : 'Sin tope configurado';
                            }),

                        Select::make('payment_method')
                            ->label('Método de pago del adelanto')
                            ->options(Advance::getPaymentMethodOptions())
                            ->default(function () {
                                $method = $this->getOwnerRecord()->activeContract?->payment_method;

                                return $method === 'cash' ? 'cash' : 'transfer';
                            })
                            ->required()
                            ->native(false)
                            ->helperText('Se completa según el contrato del empleado, pero puede modificarse.'),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Motivo u observaciones...')
                            ->rows(2),
                    ])
                    ->before(function (array $data, CreateAction $action) {
                        $employee = $this->getOwnerRecord();

                        if (! $employee->getAdvanceReferenceSalary()) {
                            Notification::make()->danger()
                                ->title('Empleado sin salario definido')
                                ->body('Los adelantos requieren que el empleado tenga salario mensual o jornal definido en su contrato activo.')
                                ->send();
                            $action->halt();
                        }

                        $maxAdvance = $employee->getMaxAdvanceAmount();
                        if ($maxAdvance !== null && (float) $data['amount'] > $maxAdvance) {
                            $percent = (int) app(PayrollSettings::class)->advance_max_percent;
                            Notification::make()->danger()
                                ->title('Monto excedido')
                                ->body('El máximo para este empleado es '.number_format($maxAdvance, 0, ',', '.').' Gs. ('.$percent.'% del salario).')
                                ->persistent()
                                ->send();
                            $action->halt();
                        }

                        $settings = app(PayrollSettings::class);
                        $maxPerPeriod = $settings->advance_max_per_period;

                        if ($maxPerPeriod > 0) {
                            $payrollType = $employee->activeContract?->payroll_type ?? 'monthly';

                            $period = PayrollPeriod::where('frequency', $payrollType)
                                ->where('start_date', '<=', now())
                                ->where('end_date', '>=', now())
                                ->first();

                            if ($period) {
                                $countInPeriod = Advance::where('employee_id', $employee->id)
                                    ->whereNotIn('status', ['cancelled', 'rejected'])
                                    ->whereBetween('created_at', [$period->start_date, $period->end_date->endOfDay()])
                                    ->count();

                                if ($countInPeriod >= $maxPerPeriod) {
                                    Notification::make()->danger()
                                        ->title('Límite de adelantos alcanzado')
                                        ->body("Este empleado ya tiene {$countInPeriod} adelanto(s) en el período actual (máximo: {$maxPerPeriod}).")
                                        ->persistent()
                                        ->send();
                                    $action->halt();
                                }
                            }
                        }
                    })
                    ->mutateFormDataUsing(function (array $data) {
                        $data['employee_id'] = $this->getOwnerRecord()->id;
                        $data['status'] = 'pending';

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Advance $record) => $record->isPending())
                    ->modalHeading('Aprobar Adelanto')
                    ->modalDescription(fn (Advance $record) => 'Se aprobará el adelanto de '.number_format((float) $record->amount, 0, ',', '.').' Gs. Se descontará en la próxima liquidación de nómina.')
                    ->modalSubmitActionLabel('Sí, aprobar')
                    ->form([
                        Select::make('payment_method')
                            ->label('Método de pago')
                            ->options(Advance::getPaymentMethodOptions())
                            ->default(fn (Advance $record) => $record->payment_method)
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Advance $record, array $data) {
                        $result = $record->approve(Auth::id(), $data['payment_method']);

                        Notification::make()
                            ->title($result['success'] ? 'Adelanto Aprobado' : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn (Advance $record) => $record->isPending())
                    ->modalHeading('Rechazar Adelanto')
                    ->modalDescription(fn (Advance $record) => 'Se rechazará el adelanto de '.number_format((float) $record->amount, 0, ',', '.').' Gs.')
                    ->modalSubmitActionLabel('Sí, rechazar')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo del rechazo')
                            ->placeholder('Ingrese el motivo...')
                            ->rows(3),
                    ])
                    ->action(function (Advance $record, array $data) {
                        $result = $record->reject($data['reason'] ?? null);

                        Notification::make()
                            ->title($result['success'] ? 'Adelanto Rechazado' : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'warning' : 'danger'}()
                            ->send();
                    }),

                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn (Advance $record) => $record->isApproved() || $record->isPaid())
                    ->url(fn (Advance $record) => route('advances.pdf', $record))
                    ->openUrlInNewTab(),
            ])
            ->emptyStateHeading('Sin adelantos registrados')
            ->emptyStateDescription('Usá el botón "Nuevo Adelanto" para registrar uno.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
