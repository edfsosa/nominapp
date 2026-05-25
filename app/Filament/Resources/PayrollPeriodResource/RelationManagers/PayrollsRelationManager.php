<?php

namespace App\Filament\Resources\PayrollPeriodResource\RelationManagers;

use App\Models\Payroll;
use App\Models\Position;
use App\Services\PayrollService;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/** Muestra y gestiona los recibos de nómina de una planilla. */
class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $title = 'Recibos de Nómina';

    protected static ?string $modelLabel = 'recibo';

    protected static ?string $pluralModelLabel = 'recibos';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Payroll $record): string => "Recibo de {$record->employee->full_name}")
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee.activeContract.position', 'approvedBy']))
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
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'employee',
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    ))
                    ->sortable()
                    ->wrap(),

                TextColumn::make('employee.activeContract.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn ($state) => Payroll::getStatusColors()[$state] ?? 'gray')
                    ->formatStateUsing(fn ($state) => Payroll::getStatusLabels()[$state] ?? $state)
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Método')
                    ->badge()
                    ->color(fn (?string $state) => $state ? (Payroll::getPaymentMethodColors()[$state] ?? 'gray') : 'gray')
                    ->icon(fn (?string $state) => $state ? (Payroll::getPaymentMethodIcons()[$state] ?? null) : null)
                    ->formatStateUsing(fn (?string $state) => $state ? (Payroll::getPaymentMethodLabels()[$state] ?? $state) : '—')
                    ->sortable(),

                TextColumn::make('base_salary')
                    ->label('Salario Base / Jornal')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->description(fn (Payroll $record): ?string => $record->employee->employment_type === 'day_laborer'
                        ? 'Jornal'
                        : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_perceptions')
                    ->label('Percepciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('danger')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gross_salary')
                    ->label('Salario Bruto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ])
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->preload()
                    ->native(false),

                SelectFilter::make('position')
                    ->label('Cargo')
                    ->options(fn () => Position::pluck('name', 'id'))
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('employee.activeContract', function (Builder $query) use ($data) {
                                $query->where('position_id', $data['value']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Payroll::getStatusLabels())
                    ->native(false),

                SelectFilter::make('payment_method')
                    ->label('Método de pago')
                    ->options(Payroll::getPaymentMethodOptions())
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
                                fn (Builder $query, $date): Builder => $query->whereDate('generated_at', '>=', $date),
                            )
                            ->when(
                                $data['generated_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('generated_at', '<=', $date),
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
                                fn (Builder $query, $amount): Builder => $query->where('net_salary', '>=', $amount),
                            )
                            ->when(
                                $data['net_salary_to'],
                                fn (Builder $query, $amount): Builder => $query->where('net_salary', '<=', $amount),
                            );
                    }),
            ])
            ->headerActions([
                Action::make('approve_all')
                    ->label('Aprobar Todos')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Todos los Recibos')
                    ->modalSubmitActionLabel('Sí, aprobar todos')
                    ->modalDescription(function () {
                        $count = $this->getOwnerRecord()->payrolls()->where('status', 'draft')->count();

                        return "Se aprobarán {$count} recibos en estado Borrador. ¿Desea continuar?";
                    })
                    ->action(function () {
                        $count = $this->getOwnerRecord()->payrolls()
                            ->where('status', 'draft')
                            ->update([
                                'status' => 'approved',
                                'approved_by_id' => Auth::id(),
                                'approved_at' => now(),
                            ]);

                        Notification::make()
                            ->success()
                            ->title("{$count} recibos aprobados")
                            ->send();
                    })
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'
                        && $this->getOwnerRecord()->payrolls()->where('status', 'draft')->exists()),

                // Marca como pagados los recibos en efectivo (approved) y los ya acreditados (disbursed)
                Action::make('mark_all_paid')
                    ->label('Marcar Todos Pagados')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar Todos como Pagados')
                    ->modalSubmitActionLabel('Sí, marcar como pagados')
                    ->modalDescription(function () {
                        $cashCount = $this->getOwnerRecord()->payrolls()
                            ->where('status', 'approved')->where('payment_method', 'cash')->count();
                        $disbursedCount = $this->getOwnerRecord()->payrolls()
                            ->where('status', 'disbursed')->count();

                        $parts = [];
                        if ($cashCount > 0) {
                            $parts[] = "{$cashCount} en efectivo aprobados";
                        }
                        if ($disbursedCount > 0) {
                            $parts[] = "{$disbursedCount} acreditados";
                        }

                        return 'Se marcarán como pagados: '.implode(' y ', $parts).'. ¿Desea continuar?';
                    })
                    ->action(function () {
                        $count = $this->getOwnerRecord()->payrolls()
                            ->where(fn ($q) => $q
                                ->where(fn ($q) => $q->where('status', 'approved')->where('payment_method', 'cash'))
                                ->orWhere('status', 'disbursed')
                            )
                            ->update(['status' => 'paid']);

                        Notification::make()
                            ->success()
                            ->title("{$count} recibos marcados como pagados")
                            ->send();
                    })
                    ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'
                        && $this->getOwnerRecord()->payrolls()
                            ->where(fn ($q) => $q
                                ->where(fn ($q) => $q->where('status', 'approved')->where('payment_method', 'cash'))
                                ->orWhere('status', 'disbursed')
                            )->exists()),

                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn () => 'recibos_'.str_replace(' ', '_', $this->getOwnerRecord()->name).'_'.now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Payroll $record) => route('filament.admin.resources.recibos.view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn (Payroll $record) => route('payrolls.download', $record))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Recibo')
                    ->modalDescription(fn (Payroll $record) => "¿Aprobar el recibo de {$record->employee->full_name} por ".Payroll::formatCurrency($record->net_salary).'?')
                    ->modalSubmitActionLabel('Sí, aprobar')
                    ->action(function (Payroll $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by_id' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recibo aprobado')
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status !== 'closed'),

                // Transición manual approved → disbursed para transferencias (sin lote bancario)
                Action::make('mark_disbursed')
                    ->label('Marcar Acreditado')
                    ->icon('heroicon-o-building-library')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Acreditado')
                    ->modalDescription(fn (Payroll $record) => "¿Confirma que el recibo de {$record->employee->full_name} fue acreditado en cuenta bancaria?")
                    ->modalSubmitActionLabel('Sí, marcar como acreditado')
                    ->action(function (Payroll $record) {
                        $record->update(['status' => 'disbursed']);

                        Notification::make()
                            ->success()
                            ->title('Recibo marcado como acreditado')
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'approved'
                        && $record->payment_method === 'transfer'
                        && $this->getOwnerRecord()->status !== 'closed'),

                // Marca como pagado: efectivo aprobado (sin pasar por disbursed) o transferencia ya acreditada
                Action::make('mark_paid')
                    ->label('Marcar Pagado')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Pagado')
                    ->modalDescription(fn (Payroll $record) => "¿Confirma que el recibo de {$record->employee->full_name} ha sido pagado?")
                    ->modalSubmitActionLabel('Sí, marcar como pagado')
                    ->action(function (Payroll $record) {
                        $record->update(['status' => 'paid']);

                        Notification::make()
                            ->success()
                            ->title('Recibo marcado como pagado')
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $this->getOwnerRecord()->status !== 'closed'
                        && (
                            ($record->status === 'approved' && $record->payment_method === 'cash')
                            || $record->status === 'disbursed'
                        )),

                // Revierte disbursed → approved solo si no está en un lote bancario
                Action::make('revert_disbursed')
                    ->label('Revertir Acreditación')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Revertir Acreditación')
                    ->modalDescription(fn (Payroll $record) => "¿Revertir el recibo de {$record->employee->full_name} a Aprobado? Se quitará el estado de acreditado.")
                    ->modalSubmitActionLabel('Sí, revertir')
                    ->action(function (Payroll $record) {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->success()
                            ->title('Acreditación revertida')
                            ->body("El recibo de {$record->employee->full_name} ha vuelto a estado Aprobado.")
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'disbursed'
                        && $record->disbursement_batch_id === null
                        && $this->getOwnerRecord()->status !== 'closed'),

                // Revierte paid → disbursed (transferencia) o paid → approved (efectivo)
                Action::make('revert_paid')
                    ->label('Revertir Pago')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Revertir Pago')
                    ->modalDescription(fn (Payroll $record) => $record->payment_method === 'transfer'
                        ? "¿Revertir el pago de {$record->employee->full_name}? Volverá a estado Acreditado."
                        : "¿Revertir el pago de {$record->employee->full_name}? Volverá a estado Aprobado.")
                    ->modalSubmitActionLabel('Sí, revertir')
                    ->action(function (Payroll $record) {
                        $newStatus = $record->payment_method === 'transfer' ? 'disbursed' : 'approved';
                        $record->update(['status' => $newStatus]);

                        $label = $newStatus === 'disbursed' ? 'Acreditado' : 'Aprobado';

                        Notification::make()
                            ->success()
                            ->title('Pago revertido')
                            ->body("El recibo de {$record->employee->full_name} ha vuelto a estado {$label}.")
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'paid' && $this->getOwnerRecord()->status !== 'closed'),

                Action::make('unapprove')
                    ->label('Desaprobar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Desaprobar Recibo')
                    ->modalDescription(fn (Payroll $record) => "¿Está seguro de desaprobar el recibo de {$record->employee->full_name}? Volverá a estado Borrador.")
                    ->modalSubmitActionLabel('Sí, desaprobar')
                    ->action(function (Payroll $record) {
                        $record->update([
                            'status' => 'draft',
                            'approved_by_id' => null,
                            'approved_at' => null,
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recibo desaprobado')
                            ->body("El recibo de {$record->employee->full_name} ha vuelto a estado Borrador.")
                            ->send();
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'approved' && $this->getOwnerRecord()->status !== 'closed'),

                Action::make('regenerate')
                    ->label('Regenerar')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar Recibo')
                    ->modalDescription(fn (Payroll $record) => "Se recalcularán todos los ítems del recibo de {$record->employee->full_name}. Esta acción reemplazará los valores actuales.")
                    ->modalSubmitActionLabel('Sí, regenerar')
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
                                ->body('Ocurrió un error al regenerar el recibo: '.$e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn (Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status !== 'closed'),

                DeleteAction::make()
                    ->visible(fn (Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status === 'draft')
                    ->successNotificationTitle('Recibo eliminado exitosamente'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar Seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Recibos Seleccionados')
                        ->modalDescription('Solo se aprobarán los recibos en estado "Borrador".')
                        ->modalSubmitActionLabel('Sí, aprobar')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    $record->update([
                                        'status' => 'approved',
                                        'approved_by_id' => Auth::id(),
                                        'approved_at' => now(),
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} recibos aprobados")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('mark_disbursed_selected')
                        ->label('Marcar Acreditados')
                        ->icon('heroicon-o-building-library')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como Acreditados')
                        ->modalDescription('Solo se marcarán los recibos de transferencia en estado "Aprobado".')
                        ->modalSubmitActionLabel('Sí, marcar como acreditados')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'approved' && $record->payment_method === 'transfer') {
                                    $record->update(['status' => 'disbursed']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} recibos marcados como acreditados")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'),

                    // Marca como pagados: efectivo aprobado o cualquier recibo acreditado
                    BulkAction::make('mark_paid_selected')
                        ->label('Marcar Pagados')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como Pagados')
                        ->modalDescription('Se marcarán como pagados los recibos en efectivo "Aprobados" y los "Acreditados".')
                        ->modalSubmitActionLabel('Sí, marcar como pagados')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                $eligible = ($record->status === 'approved' && $record->payment_method === 'cash')
                                    || $record->status === 'disbursed';
                                if ($eligible) {
                                    $record->update(['status' => 'paid']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} recibos marcados como pagados")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    // Revierte paid → disbursed (transferencia) o paid → approved (efectivo)
                    BulkAction::make('revert_paid_selected')
                        ->label('Revertir Pagos')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revertir Pagos Seleccionados')
                        ->modalDescription('Las transferencias volverán a "Acreditado"; los recibos de efectivo volverán a "Aprobado".')
                        ->modalSubmitActionLabel('Sí, revertir')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'paid') {
                                    $newStatus = $record->payment_method === 'transfer' ? 'disbursed' : 'approved';
                                    $record->update(['status' => $newStatus]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} pagos revertidos")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('unapprove_selected')
                        ->label('Desaprobar Seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Desaprobar Recibos Seleccionados')
                        ->modalDescription('¿Está seguro? Solo se desaprobarán los recibos en estado "Aprobado". Volverán a estado Borrador.')
                        ->modalSubmitActionLabel('Sí, desaprobar')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'approved') {
                                    $record->update([
                                        'status' => 'draft',
                                        'approved_by_id' => null,
                                        'approved_at' => null,
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} recibos desaprobados")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn () => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('download_pdfs')
                        ->label('Descargar PDFs')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $records->load('employee');
                            $validRecords = $records->filter(
                                fn (Payroll $r) => $r->pdf_path && Storage::disk('public')->exists($r->pdf_path)
                            );

                            if ($validRecords->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sin PDFs disponibles')
                                    ->body('Ninguno de los recibos seleccionados tiene PDF generado.')
                                    ->send();

                                return;
                            }

                            $tempDir = storage_path('app/public/temp');
                            if (! is_dir($tempDir)) {
                                mkdir($tempDir, 0755, true);
                            }

                            foreach (glob($tempDir.'/*.{pdf,zip}', GLOB_BRACE) as $file) {
                                if (is_file($file) && (time() - filemtime($file)) > 3600) {
                                    @unlink($file);
                                }
                            }

                            $uniqueId = \Illuminate\Support\Str::uuid();

                            if ($validRecords->count() === 1) {
                                $record = $validRecords->first();
                                $filename = $uniqueId.'_recibo_'.$record->employee->ci.'.pdf';
                                copy(Storage::disk('public')->path($record->pdf_path), $tempDir.'/'.$filename);
                            } else {
                                $filename = $uniqueId.'_recibos_'.now()->format('d_m_Y_H_i_s').'.zip';
                                $zip = new \ZipArchive;
                                $zip->open($tempDir.'/'.$filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                                foreach ($validRecords as $record) {
                                    $zip->addFromString(
                                        'recibo_'.$record->employee->ci.'_'.$record->id.'.pdf',
                                        Storage::disk('public')->get($record->pdf_path)
                                    );
                                }
                                $zip->close();
                            }

                            $this->js("window.open('".route('payrolls.download.temp', ['filename' => $filename])."', '_blank')");

                            Notification::make()
                                ->success()
                                ->title('Descarga iniciada')
                                ->body('Los recibos se están descargando.')
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename(fn () => 'recibos_seleccionados_'.now()->format('d_m_Y_H_i_s'))
                                ->withWriterType(Excel::XLSX),
                        ]),

                    DeleteBulkAction::make()
                        ->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
                ]),
            ])
            ->emptyStateHeading('No hay recibos generados')
            ->emptyStateDescription('Los recibos aparecerán aquí una vez que se generen desde la planilla.')
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
                            TextEntry::make('employee.activeContract.position.name')
                                ->label('Cargo')
                                ->icon('heroicon-o-briefcase')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('employee.activeContract.position.department.name')
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
                                ->label(fn (Payroll $record): string => $record->employee->employment_type === 'day_laborer'
                                    ? 'Jornal del Período'
                                    : 'Salario Base')
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

                Section::make('Estado y Aprobación')
                    ->schema([
                        Group::make([
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->color(fn ($state) => Payroll::getStatusColors()[$state] ?? 'gray')
                                ->formatStateUsing(fn ($state) => Payroll::getStatusLabels()[$state] ?? $state),

                            TextEntry::make('payment_method')
                                ->label('Método de Pago')
                                ->badge()
                                ->color(fn (?string $state) => $state ? (Payroll::getPaymentMethodColors()[$state] ?? 'gray') : 'gray')
                                ->formatStateUsing(fn (?string $state) => $state ? (Payroll::getPaymentMethodLabels()[$state] ?? $state) : '—'),

                            TextEntry::make('approvedBy.name')
                                ->label('Aprobado por')
                                ->placeholder('Sin aprobar'),
                        ])->columns(3),
                    ]),

                Section::make('Información Adicional')
                    ->schema([
                        TextEntry::make('generated_at')
                            ->label('Fecha de Generación')
                            ->dateTime('d/m/Y H:i')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('pdf_path')
                            ->label('PDF Generado')
                            ->formatStateUsing(fn ($state) => $state ? 'Disponible' : 'No generado')
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'gray')
                            ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }
}
