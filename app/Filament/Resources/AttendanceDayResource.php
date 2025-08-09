<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceDayResource\Pages;
use App\Filament\Resources\AttendanceDayResource\RelationManagers;
use App\Models\AttendanceDay;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttendanceDayResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;
    //protected static ?string $navigationGroup = 'Definiciones';
    protected static ?string $navigationLabel = 'Asistencias';
    protected static ?string $label = 'Asistencia';
    protected static ?string $pluralLabel = 'Asistencias';
    protected static ?string $slug = 'asistencias';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'id')
                    ->native(false)
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('date')
                    ->label('Fecha')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                    ])
                    ->native(false)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.ci')
                    ->label('CI')
                    ->numeric()
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'present' => 'success',
                        'absent' => 'warning',
                        'on_leave' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
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
            'index' => Pages\ManageAttendanceDays::route('/'),
        ];
    }
}
