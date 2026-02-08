<?php

namespace App\Filament\Resources\LiquidacionResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Desglose de la Liquidación';
    protected static ?string $modelLabel = 'concepto';
    protected static ?string $pluralModelLabel = 'conceptos';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'haber'     => 'success',
                        'deduction' => 'danger',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'haber'     => 'Haber',
                        'deduction' => 'Descuento',
                        default     => $state,
                    }),

                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'preaviso'          => 'Preaviso',
                        'indemnizacion'     => 'Indemnización',
                        'vacaciones'        => 'Vacaciones',
                        'aguinaldo'         => 'Aguinaldo',
                        'salario_pendiente' => 'Salario Pendiente',
                        'ips'               => 'IPS',
                        'loan'              => 'Préstamo',
                        'other'             => 'Otro',
                        default             => $state,
                    }),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->wrap(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->weight('bold')
                    ->color(fn($record) => $record->type === 'haber' ? 'success' : 'danger')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sin conceptos calculados')
            ->emptyStateDescription('Los conceptos aparecerán aquí una vez que se calcule la liquidación.')
            ->emptyStateIcon('heroicon-o-calculator');
    }
}
