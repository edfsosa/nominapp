<?php

namespace App\Filament\Resources\PayrollPeriodResource\RelationManagers;

use App\Models\Payroll;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';
    protected static ?string $title = 'Recibos';
    protected static ?string $modelLabel = 'recibo';
    protected static ?string $pluralModelLabel = 'recibos';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn(Payroll $record): string => "Recibo #{$record->id}")
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('generated_at')
                    ->label('Generado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('Ver PDF')
                    ->url(fn(Payroll $record) => route('payrolls.view', ['payroll' => $record->id]))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                ]),
            ]);
    }
}
