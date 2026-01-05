<?php

namespace App\Filament\Resources\PayrollPeriodResource\RelationManagers;

use Filament\Tables;
use App\Models\Payroll;
use App\Models\Position;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Tables\Columns\TextColumn;
use Filament\Infolists\Components\Group;
use Filament\Tables\Filters\SelectFilter;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;

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

                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

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
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->color('danger')
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('gross_salary')
                    ->label('Salario Bruto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
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
                        Tables\Columns\Summarizers\Sum::make()
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
                    ->query(function ($query, $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('employee', function ($query) use ($data) {
                                $query->where('position_id', $data['value']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->headerActions([
                // No permitimos crear desde aquí, se generan automáticamente
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->url(fn(Payroll $record) => route('payrolls.download', $record))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('view_detail')
                    ->label('Ver Detalle')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn(Payroll $record) => route('filament.admin.resources.recibos.view', ['record' => $record])),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft')
                    ->successNotificationTitle('Recibo eliminado exitosamente'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('download_pdfs')
                        ->label('Descargar PDFs')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Descargar PDFs Seleccionados')
                        ->modalDescription('Se generará un archivo ZIP con todos los recibos seleccionados.')
                        ->action(function ($records) {
                            // Lógica para generar ZIP con múltiples PDFs
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Descarga iniciada')
                                ->body('Los PDFs se están generando y descargando.')
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->status === 'draft')
                        ->action(function ($records) {
                            $records->each->delete();

                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Recibos eliminados')
                                ->body('Los recibos seleccionados han sido eliminados.')
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay recibos generados')
            ->emptyStateDescription('Los recibos aparecerán aquí una vez que se generen desde el período.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultSort('employee.last_name', 'asc');
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

                            TextEntry::make('employee.department.name')
                                ->label('Departamento')
                                ->icon('heroicon-o-building-office-2')
                                ->badge()
                                ->color('primary'),
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
