<?php

namespace App\Filament\Resources\PayrollPeriodResource\RelationManagers;

use App\Models\Payroll;
use App\Models\Position;
use App\Services\PayrollService;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\Group;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Resources\RelationManagers\RelationManager;
use Illuminate\Database\Eloquent\Builder;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';
    protected static ?string $title = 'Recibos de Nómina';
    protected static ?string $modelLabel = 'recibo';
    protected static ?string $pluralModelLabel = 'recibos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn(Payroll $record): string => "Recibo de {$record->employee->full_name}")
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee.position']))
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
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable()
                    ->wrap(),

                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('base_salary')
                    ->label('Salario Base')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('total_perceptions')
                    ->label('Percepciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('danger')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('gross_salary')
                    ->label('Salario Bruto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('net_salary')
                    ->label('Salario Neto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total a Pagar'),
                    ]),

                TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->native(false),

                SelectFilter::make('position')
                    ->label('Cargo')
                    ->options(function () {
                        return Position::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('employee', function (Builder $query) use ($data) {
                                $query->where('position_id', $data['value']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),

                Filter::make('generated_at')
                    ->label('Fecha de generación')
                    ->form([
                        DatePicker::make('generated_from')
                            ->label('Desde')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('generated_until')
                            ->label('Hasta')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['generated_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('generated_at', '>=', $date),
                            )
                            ->when(
                                $data['generated_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('generated_at', '<=', $date),
                            );
                    }),

                Filter::make('net_salary_range')
                    ->label('Rango de salario neto')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('net_salary_from')
                            ->label('Desde')
                            ->numeric()
                            ->prefix('₲')
                            ->placeholder('0'),
                        \Filament\Forms\Components\TextInput::make('net_salary_to')
                            ->label('Hasta')
                            ->numeric()
                            ->prefix('₲')
                            ->placeholder('999999999'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['net_salary_from'],
                                fn(Builder $query, $amount): Builder => $query->where('net_salary', '>=', $amount),
                            )
                            ->when(
                                $data['net_salary_to'],
                                fn(Builder $query, $amount): Builder => $query->where('net_salary', '<=', $amount),
                            );
                    }),
            ])
            ->headerActions([
                // No permitimos crear desde aquí, se generan automáticamente
            ])
            ->actions([
                ViewAction::make(),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Payroll $record) => route('payrolls.download', $record))
                    ->openUrlInNewTab(),

                Action::make('view_detail')
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn(Payroll $record) => route('filament.admin.resources.recibos.view', ['record' => $record])),

                Action::make('regenerate')
                    ->label('Regenerar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar Recibo')
                    ->modalDescription(fn(Payroll $record) => "Se recalcularán todos los ítems del recibo de {$record->employee->full_name}. Esta acción reemplazará los valores actuales.")
                    ->action(function (Payroll $record, PayrollService $payrollService) {
                        try {
                            $payrollService->regenerateForEmployee($record);

                            Notification::make()
                                ->success()
                                ->title('Recibo regenerado')
                                ->body("El recibo de {$record->employee->full_name} ha sido recalculado exitosamente.")
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al regenerar')
                                ->body('Ocurrió un error al regenerar el recibo: ' . $e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'),

                DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft')
                    ->successNotificationTitle('Recibo eliminado exitosamente'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
                ]),
            ])
            ->emptyStateHeading('No hay recibos generados')
            ->emptyStateDescription('Los recibos aparecerán aquí una vez que se generen desde el período.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('generated_at', 'desc');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información del Empleado')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.ci')
                                ->label('Cédula de Identidad')
                                ->icon('heroicon-o-identification')
                                ->copyable(),

                            TextEntry::make('employee.full_name')
                                ->label('Nombre Completo'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('employee.position.name')
                                ->label('Cargo')
                                ->icon('heroicon-o-briefcase')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('employee.position.department.name')
                                ->label('Departamento')
                                ->icon('heroicon-o-building-office-2')
                                ->badge()
                                ->color('primary')
                                ->default('N/A'),
                        ])->columns(2),
                    ]),

                Section::make('Detalle de Nómina')
                    ->schema([
                        Group::make([
                            TextEntry::make('base_salary')
                                ->label('Salario Base')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('total_perceptions')
                                ->label('Total Percepciones')
                                ->money('PYG', locale: 'es_PY')
                                ->color('success')
                                ->icon('heroicon-o-plus-circle'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('gross_salary')
                                ->label('Salario Bruto')
                                ->money('PYG', locale: 'es_PY')
                                ->weight('bold'),

                            TextEntry::make('total_deductions')
                                ->label('Total Deducciones')
                                ->money('PYG', locale: 'es_PY')
                                ->color('danger')
                                ->icon('heroicon-o-minus-circle'),
                        ])->columns(2),

                        TextEntry::make('net_salary')
                            ->label('Salario Neto a Pagar')
                            ->money('PYG', locale: 'es_PY')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->icon('heroicon-o-currency-dollar'),
                    ]),

                Section::make('Información Adicional')
                    ->schema([
                        TextEntry::make('generated_at')
                            ->label('Fecha de Generación')
                            ->dateTime('d/m/Y H:i')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('pdf_path')
                            ->label('PDF Generado')
                            ->formatStateUsing(fn($state) => $state ? 'Disponible' : 'No generado')
                            ->badge()
                            ->color(fn($state) => $state ? 'success' : 'gray')
                            ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
