<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmployeePerceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePerceptions';
    protected static ?string $title = 'Percepciones';
    protected static ?string $modelLabel = 'Percepción';
    protected static ?string $pluralModelLabel = 'Percepciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('perception_id')
                    ->label('Percepción')
                    ->relationship(
                        name: 'perception',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn(Model $record) =>
                        $record->name . ' (' .
                            ($record->calculation === 'fixed'
                                ? '₲ ' . number_format($record->amount, 0, ',', '.')
                                : $record->percent . '%'
                            ) . ')'
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn($state, callable $set) => $set('custom_amount', null)),

                TextInput::make('custom_amount')
                    ->label('Monto personalizado')
                    ->numeric()
                    ->prefix('₲')
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->helperText('Dejar vacío para usar el monto configurado en la percepción')
                    ->nullable(),

                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->maxDate(fn(Get $get) => $get('end_date')),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->native(false)
                    ->after('start_date')
                    ->minDate(fn(Get $get) => $get('start_date')),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('perception.code')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('perception.name')
                    ->label('Percepción')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('perception.calculation')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'fixed' => 'Fijo',
                        'percentage' => 'Porcentaje',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'fixed' => 'success',
                        'percentage' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount_display')
                    ->label('Monto')
                    ->getStateUsing(function ($record) {
                        // Si hay monto personalizado, mostrarlo
                        if ($record->custom_amount !== null) {
                            return '₲ ' . number_format($record->custom_amount, 0, ',', '.');
                        }

                        // Si no, mostrar el monto de la percepción según su tipo
                        if ($record->perception->calculation === 'percentage') {
                            return $record->perception->percent . '%';
                        }

                        return '₲ ' . number_format($record->perception->amount, 0, ',', '.');
                    })
                    ->badge()
                    ->color(fn($record) => $record->custom_amount !== null ? 'warning' : 'gray'),

                TextColumn::make('start_date')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Indefinido')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->notes)
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
                Tables\Filters\SelectFilter::make('perception_id')
                    ->label('Percepción')
                    ->relationship('perception', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('activas')
                    ->label('Solo activas')
                    ->query(fn($query) => $query->where(function ($q) {
                        $q->where('end_date', '>=', now())
                            ->orWhereNull('end_date');
                    }))
                    ->default(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar percepción')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay percepciones')
            ->emptyStateDescription('Comienza agregando una percepción al empleado')
            ->emptyStateIcon('heroicon-o-plus-circle');
    }
}
