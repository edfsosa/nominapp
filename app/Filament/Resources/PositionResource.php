<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PositionResource\Pages;
use App\Filament\Resources\PositionResource\RelationManagers;
use App\Models\Position;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PositionResource extends Resource
{
    protected static ?string $model = Position::class;
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?string $navigationLabel = 'Cargos';
    protected static ?string $label = 'Cargo';
    protected static ?string $pluralLabel = 'Cargos';
    protected static ?string $slug = 'cargos';
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Cargo')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre del Cargo')
                            ->placeholder('Ejemplo: Gerente de Ventas')
                            ->required()
                            ->maxLength(60)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Select::make('department_id')
                            ->label('Departamento')
                            ->placeholder('Seleccione un departamento')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label('Nombre del Departamento')
                                    ->required()
                                    ->maxLength(60),
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->weight('bold')
                    ->icon('heroicon-o-briefcase')
                    ->iconColor('primary'),

                TextColumn::make('department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->sortable(),

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
                SelectFilter::make('department')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No hay cargos registrados')
            ->emptyStateDescription('Comienza a agregar los cargos de tu empresa para asignarlos a los empleados.')
            ->emptyStateIcon('heroicon-o-briefcase');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EmployeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPositions::route('/'),
            'create' => Pages\CreatePosition::route('/create'),
            'view' => Pages\ViewPosition::route('/{record}'),
            'edit' => Pages\EditPosition::route('/{record}/edit'),
        ];
    }
}
