<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Resources\PayrollResource\RelationManagers;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Recibos';
    protected static ?string $label = 'Recibo';
    protected static ?string $pluralLabel = 'Recibos';
    protected static ?string $slug = 'recibos';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Recibo')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'id')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->getOptionLabelFromRecordUsing(function ($record) {
                                return "{$record->first_name} {$record->last_name} - CI: {$record->ci}";
                            })
                            ->columnSpan(1),

                        Select::make('payroll_period_id')
                            ->label('Período')
                            ->relationship('period', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Salarios')
                    ->schema([
                        TextInput::make('base_salary')
                            ->label(fn(?Payroll $record): string => $record?->employee?->employment_type === 'day_laborer'
                                ? 'Jornal del Período'
                                : 'Salario Base')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->helperText(fn(?Payroll $record): string => $record?->employee?->employment_type === 'day_laborer'
                                ? 'Tarifa diaria × días trabajados'
                                : 'Salario base del empleado')
                            ->columnSpan(1),

                        TextInput::make('gross_salary')
                            ->label('Salario Bruto')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->helperText('Salario base + percepciones')
                            ->columnSpan(1),

                        TextInput::make('total_perceptions')
                            ->label('Total Percepciones')
                            ->numeric()
                            ->prefix('₲')
                            ->default(0.00)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('total_deductions')
                            ->label('Total Deducciones')
                            ->numeric()
                            ->prefix('₲')
                            ->default(0.00)
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('net_salary')
                            ->label('Salario Neto')
                            ->numeric()
                            ->prefix('₲')
                            ->required()
                            ->helperText('Salario bruto - deducciones')
                            ->columnSpan(2),
                    ])
                    ->columns(2),

                Section::make('Información Adicional')
                    ->schema([
                        DateTimePicker::make('generated_at')
                            ->label('Fecha de Generación')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->disabled()
                            ->dehydrated()
                            ->default(now())
                            ->columnSpan(1),

                        TextInput::make('pdf_path')
                            ->label('Ruta del PDF')
                            ->disabled()
                            ->dehydrated()
                            ->columnSpan(1),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('period.name')
                    ->label('Período')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),

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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('gross_salary')
                    ->label('Salario Bruto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('net_salary')
                    ->label('Salario Neto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('approvedBy.name')
                    ->label('Aprobado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_at')
                    ->label('Fecha Aprobación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('generated_at')
                    ->label('Generado')
                    ->dateTime('d/m/Y H:i')
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
                SelectFilter::make('payroll_period_id')
                    ->label('Período')
                    ->relationship('period', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->first_name} {$record->last_name}";
                    }),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft'    => 'Borrador',
                        'approved' => 'Aprobado',
                        'paid'     => 'Pagado',
                    ])
                    ->native(false),

                Filter::make('current_year')
                    ->label('Año Actual')
                    ->query(fn($query) => $query->whereHas('period', function ($q) {
                        $q->whereYear('start_date', now()->year);
                    }))
                    ->default(),
            ])
            ->actions([
                ViewAction::make(),

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
                    ->modalDescription(fn(Payroll $record) => "¿Está seguro de aprobar el recibo de {$record->employee->full_name} por " . Payroll::formatCurrency($record->net_salary) . "?")
                    ->action(function (Payroll $record) {
                        $record->update([
                            'status' => 'approved',
                            'approved_by_id' => Auth::id(),
                            'approved_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recibo aprobado')
                            ->body("El recibo de {$record->employee->full_name} ha sido aprobado.")
                            ->send();
                    })
                    ->visible(fn(Payroll $record) => $record->status === 'draft'),

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
                    ->visible(fn(Payroll $record) => $record->status === 'approved'),

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
                    ->visible(fn(Payroll $record) => $record->status === 'paid'),

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
                    ->visible(fn(Payroll $record) => $record->status === 'approved'),

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
                    ->visible(fn(Payroll $record) => $record->status === 'draft'),

                EditAction::make()
                    ->visible(fn(Payroll $record) => $record->status === 'draft'),

                DeleteAction::make()
                    ->visible(fn(Payroll $record) => $record->status === 'draft'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobar Seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Recibos Seleccionados')
                        ->modalDescription('¿Está seguro de aprobar todos los recibos seleccionados? Solo se aprobarán los que estén en estado "Borrador".')
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
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('mark_paid_selected')
                        ->label('Marcar Pagados')
                        ->icon('heroicon-o-banknotes')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Marcar como Pagados')
                        ->modalDescription('¿Confirma que los recibos seleccionados han sido pagados? Solo se marcarán los que estén en estado "Aprobado".')
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
                        ->deselectRecordsAfterCompletion(),

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
                        ->deselectRecordsAfterCompletion(),

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
                        ->action(function (Collection $records) {
                            $deleted = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    $record->delete();
                                    $deleted++;
                                }
                            }

                            if ($deleted > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("{$deleted} recibos eliminados")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Solo se pueden eliminar recibos en borrador')
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay recibos de nómina')
            ->emptyStateDescription('Los recibos se generan automáticamente desde los períodos de nómina.')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Empleado')
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
                                ->color('primary'),

                            TextEntry::make('employee.employment_type')
                                ->label('Tipo de Remuneración')
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'day_laborer' => 'Jornalero (Jornal Diario)',
                                    default       => 'Mensualizado (Sueldo)',
                                })
                                ->icon(fn(string $state): string => match ($state) {
                                    'day_laborer' => 'heroicon-o-calendar-days',
                                    default       => 'heroicon-o-banknotes',
                                })
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'day_laborer' => 'warning',
                                    default       => 'info',
                                }),
                        ])->columns(3),
                    ]),

                InfolistSection::make('Información del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('period.name')
                                ->label('Período')
                                ->icon('heroicon-o-calendar-days')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('period.frequency')
                                ->label('Frecuencia')
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'monthly'  => 'Mensual',
                                    'biweekly' => 'Quincenal',
                                    'weekly'   => 'Semanal',
                                    default    => $state,
                                })
                                ->badge(),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('period.start_date')
                                ->label('Fecha Inicio')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('period.end_date')
                                ->label('Fecha Fin')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Detalle de Nómina')
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

                InfolistSection::make('Estado y Aprobación')
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
                                ->placeholder('Sin aprobar')
                                ->icon('heroicon-o-user'),
                        ])->columns(2),

                        TextEntry::make('approved_at')
                            ->label('Fecha de Aprobación')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sin aprobar')
                            ->icon('heroicon-o-clock'),
                    ]),

                InfolistSection::make('Información del Sistema')
                    ->schema([
                        Group::make([
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
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('created_at')
                                ->label('Creado')
                                ->dateTime('d/m/Y H:i'),

                            TextEntry::make('updated_at')
                                ->label('Actualizado')
                                ->dateTime('d/m/Y H:i'),
                        ])->columns(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'view' => Pages\ViewPayroll::route('/{record}'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
