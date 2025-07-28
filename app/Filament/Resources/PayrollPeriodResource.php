<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollPeriodResource\Pages;
use App\Filament\Resources\PayrollPeriodResource\RelationManagers;
use App\Models\PayrollPeriod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Periodos';
    protected static ?string $label = 'Periodo';
    protected static ?string $pluralLabel = 'Periodos';
    protected static ?string $slug = 'periodos';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('frequency')
                    ->label('Frecuencia')
                    ->options([
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                    ])
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha inicio')
                    ->format('d/m/Y')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha fin')
                    ->format('d/m/Y')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('frequency')
                    ->label('Fecha fin'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Fecha fin')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Fecha fin')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePayrollPeriods::route('/'),
        ];
    }
}
