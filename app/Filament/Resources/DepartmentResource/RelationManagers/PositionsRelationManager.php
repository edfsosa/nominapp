<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class PositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'positions';
    protected static ?string $title = 'Cargos';
    protected static ?string $modelLabel = 'cargo';
    protected static ?string $pluralModelLabel = 'cargos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del cargo')
                    ->placeholder('Ej: Gerente de Ventas, Analista de RRHH...')
                    ->required()
                    ->maxLength(255)
                    ->unique('positions', 'name', ignorable: fn($record) => $record)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('con_empleados')
                    ->query(fn($query) => $query->has('employees'))
                    ->label('Con empleados asignados'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nuevo Cargo')
                    ->icon('heroicon-o-plus')
                    ->successNotificationTitle('Cargo creado exitosamente'),
            ])
            ->actions([
                ViewAction::make('viewEmployees')
                    ->label('Ver empleados')
                    ->icon('heroicon-o-users')
                    ->modalHeading(fn($record) => "Empleados en: {$record->name}")
                    ->infolist(
                        fn(Infolist $infolist) => $infolist
                            ->schema([
                                RepeatableEntry::make('employees')
                                    ->label('')
                                    ->schema([
                                        TextEntry::make('first_name')
                                            ->label('Nombre'),
                                        TextEntry::make('last_name')
                                            ->label('Apellido'),
                                        TextEntry::make('email')
                                            ->label('Email')
                                            ->icon('heroicon-o-envelope'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])
                    )
                    ->modalWidth('6xl')
                    ->color('info')
                    ->visible(fn($record) => $record->employees_count > 0),
                EditAction::make()
                    ->successNotificationTitle('Cargo actualizado exitosamente'),
                DeleteAction::make()
                    ->successNotificationTitle('Cargo eliminado exitosamente'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->successNotificationTitle('Cargos eliminados exitosamente'),
                ]),
            ])
            ->emptyStateHeading('No hay cargos registrados')
            ->emptyStateDescription('Comienza creando un nuevo cargo para este departamento.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }
}
