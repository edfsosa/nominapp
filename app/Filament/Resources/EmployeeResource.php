<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Filament\Resources\EmployeeResource\RelationManagers\PayrollsRelationManager;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('ci')
                    ->integer()
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('salary')
                    ->numeric()
                    ->required(),
                Select::make('department')
                    ->options(fn() => [
                        'Contabilidad' => 'Contabilidad',
                        'Ventas' => 'Ventas',
                        'TI' => 'TI',
                    ]),
                Select::make('branch')
                    ->options([
                        'Asuncion' => 'Asunción',
                        'Luque' => 'Luque',
                        'Capiata' => 'Capiatá',
                    ])
                    ->required()
                    ->native(false),
                Radio::make('contract_type')
                    ->options([
                        'mensualero' => 'Mensualero',
                        'jornalero' => 'Jornalero',
                    ])
                    ->inline()
                    ->required(),
                DatePicker::make('hire_date')
                    ->required(),
                FileUpload::make('documents')
                    ->directory('employees/documents'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ci')
                    ->searchable(),
                TextColumn::make('full_name') // Usa el accessor del modelo
                    ->searchable(['first_name', 'last_name']),
                TextColumn::make('department'),

                TextColumn::make('salary')
                    ->money('PGY'),
                TextColumn::make('branch')
                    ->searchable(),
                TextColumn::make('contract_type')
                    ->searchable(),
                IconColumn::make('status')
                    ->icon(fn(string $state): string => match ($state) {
                        'inactivo' => 'heroicon-o-x-circle',
                        'activo' => 'heroicon-o-check-circle',
                    }),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PayrollsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
