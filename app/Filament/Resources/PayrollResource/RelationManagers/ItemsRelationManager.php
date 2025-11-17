<?php

namespace App\Filament\Resources\PayrollResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Items';
    protected static ?string $modelLabel = 'item';
    protected static ?string $pluralModelLabel = 'items';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->sortable()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'perception' => 'Percepción',
                        'deduction' => 'Deducción'
                    }),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', 0)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'perception' => 'Percepción',
                        'deduction' => 'Deducción',
                    ])
                    ->native(false),
            ])
            ->headerActions([

            ])
            ->actions([

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([

                ]),
            ]);
    }
}
