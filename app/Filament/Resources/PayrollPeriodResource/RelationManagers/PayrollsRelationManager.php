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
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee.position', 'approvedBy']))
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

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft'    => 'gray',
                        'approved' => 'success',
                        'paid'     => 'info',
                        default    => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft'    => 'Borrador',
                        'approved' => 'Aprobado',
                        'paid'     => 'Pagado',
                        default    => $state,
                    })
                    ->sortable(),

                TextColumn::make('base_salary')
                    ->label('Salario Base / Jornal')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->description(fn(Payroll $record): ?string => $record->employee->employment_type === 'day_laborer'
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

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft'    => 'Borrador',
                        'approved' => 'Aprobado',
                        'paid'     => 'Pagado',
                    ])
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
                Action::make('approve_all')
                    ->label('Aprobar Todos')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Todos los Recibos')
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
                    ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'
                        && $this->getOwnerRecord()->payrolls()->where('status', 'draft')->exists()),

                Action::make('mark_all_paid')
                    ->label('Marcar Todos Pagados')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar Todos como Pagados')
                    ->modalDescription(function () {
                        $count = $this->getOwnerRecord()->payrolls()->where('status', 'approved')->count();
                        return "Se marcarán {$count} recibos aprobados como pagados. ¿Desea continuar?";
                    })
                    ->action(function () {
                        $count = $this->getOwnerRecord()->payrolls()
                            ->where('status', 'approved')
                            ->update(['status' => 'paid']);

                        Notification::make()
                            ->success()
                            ->title("{$count} recibos marcados como pagados")
                            ->send();
                    })
                    ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'
                        && $this->getOwnerRecord()->payrolls()->where('status', 'approved')->exists()),

                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn() => 'recibos_' . str_replace(' ', '_', $this->getOwnerRecord()->name) . '_' . now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn(Payroll $record) => route('filament.admin.resources.recibos.view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Payroll $record) => route('payrolls.download', $record))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Recibo')
                    ->modalDescription(fn(Payroll $record) => "¿Aprobar el recibo de {$record->employee->full_name} por " . Payroll::formatCurrency($record->net_salary) . "?")
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
                    ->visible(fn(Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status !== 'closed'),

                Action::make('mark_paid')
                    ->label('Marcar Pagado')
                    ->icon('heroicon-o-banknotes')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Marcar como Pagado')
                    ->modalDescription(fn(Payroll $record) => "¿Confirma que el recibo de {$record->employee->full_name} ha sido pagado?")
                    ->action(function (Payroll $record) {
                        $record->update(['status' => 'paid']);

                        Notification::make()
                            ->success()
                            ->title('Recibo marcado como pagado')
                            ->send();
                    })
                    ->visible(fn(Payroll $record) => $record->status === 'approved' && $this->getOwnerRecord()->status !== 'closed'),

                Action::make('revert_paid')
                    ->label('Revertir Pago')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Revertir Pago')
                    ->modalDescription(fn(Payroll $record) => "¿Está seguro de revertir el pago del recibo de {$record->employee->full_name}? Volverá a estado Aprobado.")
                    ->action(function (Payroll $record) {
                        $record->update(['status' => 'approved']);

                        Notification::make()
                            ->success()
                            ->title('Pago revertido')
                            ->body("El recibo de {$record->employee->full_name} ha vuelto a estado Aprobado.")
                            ->send();
                    })
                    ->visible(fn(Payroll $record) => $record->status === 'paid' && $this->getOwnerRecord()->status !== 'closed'),

                Action::make('unapprove')
                    ->label('Desaprobar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Desaprobar Recibo')
                    ->modalDescription(fn(Payroll $record) => "¿Está seguro de desaprobar el recibo de {$record->employee->full_name}? Volverá a estado Borrador.")
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
                    ->visible(fn(Payroll $record) => $record->status === 'approved' && $this->getOwnerRecord()->status !== 'closed'),

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
                    ->visible(fn(Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status !== 'closed'),

                DeleteAction::make()
                    ->visible(fn(Payroll $record) => $record->status === 'draft' && $this->getOwnerRecord()->status === 'draft')
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
                        ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('mark_paid_selected')
                        ->label('Marcar Pagados')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como Pagados')
                        ->modalDescription('Solo se marcarán los recibos en estado "Aprobado".')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'approved') {
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

                    BulkAction::make('revert_paid_selected')
                        ->label('Revertir Pagos')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Revertir Pagos Seleccionados')
                        ->modalDescription('¿Está seguro? Solo se revertirán los recibos en estado "Pagado". Volverán a estado Aprobado.')
                        ->action(function (Collection $records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'paid') {
                                    $record->update(['status' => 'approved']);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title("{$count} pagos revertidos")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('unapprove_selected')
                        ->label('Desaprobar Seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Desaprobar Recibos Seleccionados')
                        ->modalDescription('¿Está seguro? Solo se desaprobarán los recibos en estado "Aprobado". Volverán a estado Borrador.')
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
                        ->visible(fn() => $this->getOwnerRecord()->status !== 'closed'),

                    BulkAction::make('download_pdfs')
                        ->label('Descargar PDFs')
                        ->icon('heroicon-o-document-arrow-down')
                        ->color('info')
                        ->action(function (Collection $records) {
                            $records->load('employee');
                            $validRecords = $records->filter(
                                fn(Payroll $r) => $r->pdf_path && Storage::disk('public')->exists($r->pdf_path)
                            );

                            if ($validRecords->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sin PDFs disponibles')
                                    ->body('Ninguno de los recibos seleccionados tiene PDF generado.')
                                    ->send();
                                return;
                            }

                            if ($validRecords->count() === 1) {
                                $record = $validRecords->first();
                                return response()->streamDownload(function () use ($record) {
                                    echo Storage::disk('public')->get($record->pdf_path);
                                }, 'recibo_' . $record->employee->ci . '.pdf', ['Content-Type' => 'application/pdf']);
                            }

                            $zipFileName = 'recibos_' . now()->format('d_m_Y_H_i_s') . '.zip';
                            $zipPath = storage_path('app/temp/' . $zipFileName);

                            if (!is_dir(storage_path('app/temp'))) {
                                mkdir(storage_path('app/temp'), 0755, true);
                            }

                            $zip = new \ZipArchive();
                            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

                            foreach ($validRecords as $record) {
                                $pdfContent = Storage::disk('public')->get($record->pdf_path);
                                $zip->addFromString(
                                    'recibo_' . $record->employee->ci . '_' . $record->id . '.pdf',
                                    $pdfContent
                                );
                            }

                            $zip->close();

                            return response()->streamDownload(function () use ($zipPath) {
                                echo file_get_contents($zipPath);
                                @unlink($zipPath);
                            }, $zipFileName, ['Content-Type' => 'application/zip']);
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename(fn() => 'recibos_seleccionados_' . now()->format('d_m_Y_H_i_s'))
                                ->withWriterType(Excel::XLSX),
                        ]),

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
                                ->label(fn(Payroll $record): string => $record->employee->employment_type === 'day_laborer'
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
                                ->color(fn(string $state): string => match ($state) {
                                    'draft'    => 'gray',
                                    'approved' => 'success',
                                    'paid'     => 'info',
                                    default    => 'gray',
                                })
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'draft'    => 'Borrador',
                                    'approved' => 'Aprobado',
                                    'paid'     => 'Pagado',
                                    default    => $state,
                                }),

                            TextEntry::make('approvedBy.name')
                                ->label('Aprobado por')
                                ->placeholder('Sin aprobar'),
                        ])->columns(2),
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
