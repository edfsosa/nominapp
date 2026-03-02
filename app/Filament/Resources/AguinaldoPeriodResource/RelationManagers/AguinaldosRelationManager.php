<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\RelationManagers;

use App\Models\Aguinaldo;
use App\Models\Position;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
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

class AguinaldosRelationManager extends RelationManager
{
    protected static string $relationship = 'aguinaldos';
    protected static ?string $title = 'Aguinaldos Generados';
    protected static ?string $modelLabel = 'aguinaldo';
    protected static ?string $pluralModelLabel = 'aguinaldos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn(Aguinaldo $record): string => "Aguinaldo de {$record->employee->full_name}")
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee.activeContract.position']))
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

                TextColumn::make('employee.activeContract.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('months_worked')
                    ->label('Meses')
                    ->alignCenter()
                    ->formatStateUsing(fn($state) => number_format($state, 0))
                    ->sortable(),

                TextColumn::make('total_earned')
                    ->label('Total Devengado')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable()
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('aguinaldo_amount')
                    ->label('Aguinaldo')
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

                Filter::make('aguinaldo_range')
                    ->label('Rango de aguinaldo')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('aguinaldo_from')
                            ->label('Desde')
                            ->numeric()
                            ->prefix('₲')
                            ->placeholder('0'),
                        \Filament\Forms\Components\TextInput::make('aguinaldo_to')
                            ->label('Hasta')
                            ->numeric()
                            ->prefix('₲')
                            ->placeholder('999999999'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['aguinaldo_from'],
                                fn(Builder $query, $amount): Builder => $query->where('aguinaldo_amount', '>=', $amount),
                            )
                            ->when(
                                $data['aguinaldo_to'],
                                fn(Builder $query, $amount): Builder => $query->where('aguinaldo_amount', '<=', $amount),
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
                    ->url(fn(Aguinaldo $record) => route('aguinaldos.download', $record))
                    ->openUrlInNewTab(),

                DeleteAction::make()
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft')
                    ->successNotificationTitle('Aguinaldo eliminado exitosamente'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
                ]),
            ])
            ->emptyStateHeading('No hay aguinaldos generados')
            ->emptyStateDescription('Los aguinaldos aparecerán aquí una vez que se generen desde el período.')
            ->emptyStateIcon('heroicon-o-gift')
            ->defaultSort('aguinaldo_amount', 'desc');
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

                Section::make('Cálculo del Aguinaldo')
                    ->schema([
                        Group::make([
                            TextEntry::make('total_earned')
                                ->label('Total Devengado en el Año')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('months_worked')
                                ->label('Meses Trabajados')
                                ->formatStateUsing(fn($state) => number_format($state, 0) . ' meses')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(2),

                        TextEntry::make('aguinaldo_amount')
                            ->label('Aguinaldo a Pagar (1/12 del total)')
                            ->money('PYG', locale: 'es_PY')
                            ->size('lg')
                            ->weight('bold')
                            ->color('success')
                            ->icon('heroicon-o-gift'),
                    ]),

                Section::make('Desglose Mensual')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->label('')
                            ->schema([
                                TextEntry::make('month')
                                    ->label('Mes'),
                                TextEntry::make('base_salary')
                                    ->label('Salario Base')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('perceptions')
                                    ->label('Percepciones')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('extra_hours')
                                    ->label('Horas Extras')
                                    ->money('PYG', locale: 'es_PY'),
                                TextEntry::make('total')
                                    ->label('Total')
                                    ->money('PYG', locale: 'es_PY')
                                    ->weight('bold'),
                            ])
                            ->columns(5),
                    ])
                    ->collapsible(),

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
