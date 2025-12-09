<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Filament\Resources\DepartmentResource\RelationManagers;
use App\Models\Department;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $slug = 'departamentos';
    protected static ?string $navigationLabel = 'Departamentos';
    protected static ?string $label = 'Departamento';
    protected static ?string $pluralLabel = 'Departamentos';
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del departamento')
                    ->description('Datos del departamento')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del departamento')
                            ->placeholder('Ej: Recursos Humanos, Ventas, IT...')
                            ->required()
                            ->maxLength(60)
                            ->unique(Department::class, 'name', ignoreRecord: true)
                            ->autocapitalize('words')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Departamento')
                    ->icon('heroicon-o-building-library')
                    ->sortable()
                    ->searchable()
                    ->weight('medium'),

                TextColumn::make('positions_count')
                    ->label('Cargos')
                    ->counts('positions')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->getStateUsing(fn($record) => $record->positions()->withCount('employees')->get()->sum('employees_count'))
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn($record) => $record->created_at->format('d/m/Y H:i'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->filters([
                Filter::make('with_positions')
                    ->label('Con cargos')
                    ->query(fn($query) => $query->has('positions'))
                    ->toggle(),

                Filter::make('without_positions')
                    ->label('Sin cargos')
                    ->query(fn($query) => $query->doesntHave('positions'))
                    ->toggle(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar departamentos')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estos departamentos? Los cargos asociados también serán afectados.'),
                ]),
            ])
            ->emptyStateHeading('No hay departamentos registrados')
            ->emptyStateDescription('Comienza agregando el primer departamento de tu empresa')
            ->emptyStateIcon('heroicon-o-building-library');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PositionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
