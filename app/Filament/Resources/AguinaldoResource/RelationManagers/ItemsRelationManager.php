<?php

namespace App\Filament\Resources\AguinaldoResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Desglose Mensual';
    protected static ?string $modelLabel = 'mes';
    protected static ?string $pluralModelLabel = 'meses';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('month')
                    ->label('Mes')
                    ->weight('bold'),

                TextColumn::make('base_salary')
                    ->label('Salario Base')
                    ->money('PYG', locale: 'es_PY')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('perceptions')
                    ->label('Percepciones')
                    ->money('PYG', locale: 'es_PY')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('extra_hours')
                    ->label('Horas Extras')
                    ->money('PYG', locale: 'es_PY')
                    ->color('info')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total'),
                    ]),

                TextColumn::make('total')
                    ->label('Total del Mes')
                    ->money('PYG', locale: 'es_PY')
                    ->weight('bold')
                    ->color('primary')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total Anual'),
                    ]),
            ])
            ->defaultSort('id')
            ->paginated(false)
            ->emptyStateHeading('Sin desglose mensual')
            ->emptyStateDescription('No hay datos de desglose mensual para este aguinaldo.')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
