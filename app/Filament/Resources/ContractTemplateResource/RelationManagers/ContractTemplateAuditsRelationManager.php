<?php

namespace App\Filament\Resources\ContractTemplateResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Historial de cambios de la plantilla de contrato via laravel-auditing. */
class ContractTemplateAuditsRelationManager extends RelationManager
{
    protected static string $relationship = 'audits';

    protected static ?string $recordTitleAttribute = 'id';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Historial de cambios';
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    private function translateEvent(string $event): string
    {
        return match ($event) {
            'created'  => 'Creado',
            'updated'  => 'Modificado',
            'deleted'  => 'Eliminado',
            'restored' => 'Restaurado',
            default    => ucfirst($event),
        };
    }

    private function eventColor(string $event): string
    {
        return match ($event) {
            'created'  => 'success',
            'updated'  => 'info',
            'deleted'  => 'danger',
            'restored' => 'warning',
            default    => 'gray',
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user')->orderBy('created_at', 'desc'))
            ->emptyStateHeading('Sin historial de cambios')
            ->emptyStateDescription('Los cambios en los textos y configuración de la plantilla aparecerán aquí.')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->searchable(),

                Tables\Columns\TextColumn::make('event')
                    ->label('Evento')
                    ->formatStateUsing(fn (string $state) => $this->translateEvent($state))
                    ->badge()
                    ->color(fn (string $state) => $this->eventColor($state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('old_values')
                    ->label('Valores anteriores')
                    ->formatStateUsing(fn (Column $column, $record) => $this->getOwnerRecord()->formatAuditFieldsForPresentation($column->getName(), $record))
                    ->html()
                    ->wrap(),

                Tables\Columns\TextColumn::make('new_values')
                    ->label('Nuevos valores')
                    ->formatStateUsing(fn (Column $column, $record) => $this->getOwnerRecord()->formatAuditFieldsForPresentation($column->getName(), $record))
                    ->html()
                    ->wrap(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
