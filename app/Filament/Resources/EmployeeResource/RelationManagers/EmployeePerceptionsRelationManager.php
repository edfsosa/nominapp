<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(fn (Model $record) => $record->name . ' (' . ($record->calculation === 'fixed' ? $record->amount . ' PYG' : $record->percent . '%') . ')')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('custom_amount')
                    ->label('Monto personalizado')
                    ->integer()
                    ->helperText('Si se deja en blanco, se usará el monto predeterminado de la percepción.')
                    ->nullable(),
                DatePicker::make('start_date')
                    ->label('Desde')
                    ->native(false)
                    ->default(now())
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Hasta')
                    ->native(false)
                    ->after('start_date'),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(1)
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
                    ->searchable(),
                TextColumn::make('perception.calculation')
                    ->label('Cálculo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'fixed' => 'Fijo',
                        'percentage' => 'Porcentaje',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('perception.amount')
                    ->label('Monto')
                    ->money('PYG', true)
                    ->sortable(),
                TextColumn::make('perception.percent')
                    ->label('Porcentaje')
                    ->suffix('%')
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Desde')
                    ->sortable()
                    ->date('d/m/Y'),
                TextColumn::make('end_date')
                    ->label('Hasta')
                    ->sortable()
                    ->date('d/m/Y'),
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
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
