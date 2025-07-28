<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceEventResource\Pages;
use App\Filament\Resources\AttendanceEventResource\RelationManagers;
use App\Models\AttendanceEvent;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttendanceEventResource extends Resource
{
    protected static ?string $model = AttendanceEvent::class;
    //protected static ?string $navigationGroup = 'Definiciones';
    protected static ?string $navigationLabel = 'Marcaciones';
    protected static ?string $label = 'Marcación';
    protected static ?string $pluralLabel = 'Marcaciones';
    protected static ?string $slug = 'marcaciones';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    /* public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('attendance_day_id')
                    ->label('Día')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('event_type')
                    ->label('Tipo')
                    ->required(),
                Forms\Components\Textarea::make('location')
                    ->label('Ubicación')
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('Grabado')
                    ->label('Empleado')
                    ->required(),
            ]);
    } */

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Marcado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipo')
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
                SelectFilter::make('employee')
                    ->relationship('employee', 'ci')
                    ->label('Empleado')
                    ->placeholder('Seleccionar empleado')
                    ->options(function (Builder $query) {
                        return $query->pluck('ci', 'id');
                    })
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false),
                SelectFilter::make('event_type')
                    ->label('Tipo')
                    ->placeholder('Seleccionar tipo')
                    ->options([
                        'check_in' => 'Entrada jornada',
                        'break_start' => 'Salida descanso',
                        'break_end' => 'Entrada descanso',
                        'check_out' => 'Salida jornada',
                    ])
                    ->native(false),
                Filter::make('recorded_at')
                    ->form([
                        DatePicker::make('recorded_from')
                            ->label('Desde')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('recorded_until')
                            ->label('Hasta')
                            ->format('d/m/Y')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['recorded_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('recorded_at', '>=', $date),
                            )
                            ->when(
                                $data['recorded_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('recorded_at', '<=', $date),
                            );
                    })
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
            'index' => Pages\ManageAttendanceEvents::route('/'),
        ];
    }
}
