<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VacationResource\Pages;
use App\Filament\Resources\VacationResource\RelationManagers;
use App\Models\Vacation;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VacationResource extends Resource
{
    protected static ?string $model = Vacation::class;
    protected static ?string $navigationLabel = 'Vacaciones';
    protected static ?string $label = 'vacación';
    protected static ?string $pluralLabel = 'vacaciones';
    protected static ?string $slug = 'vacaciones';
    protected static ?string $navigationIcon = 'heroicon-o-sun';
    protected static ?string $navigationGroup = 'Empleados';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'id', function ($query) {
                        $query->where('status', 'active'); // Filtrar solo empleados activos
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->getOptionLabelFromRecordUsing(function ($record) {
                        return "{$record->first_name} {$record->last_name}"; // Combinar first_name y last_name
                    }),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->default('pending')
                    ->native(false)
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->displayFormat('d/m/Y')
                    ->native(false)
                    ->closeOnDateSelection()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                        default => $state,
                    }),
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
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccionar estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(
                        function ($record) {
                            if ($record->status !== 'pending') {
                                Notification::make()
                                    ->title('Solo se pueden aprobar solicitudes pendientes.')
                                    ->danger()
                                    ->send();
                                return;
                            } else {
                                $record->update(['status' => 'approved']);
                                Notification::make()
                                    ->title("Solicitud de vacaciones #{$record->id} aprobada.")
                                    ->success()
                                    ->send();
                            }
                        }
                    )
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->action(
                        function ($record) {
                            if ($record->status !== 'pending') {
                                Notification::make()
                                    ->title('Solo se pueden rechazar solicitudes pendientes.')
                                    ->danger()
                                    ->send();
                                return;
                            } else {
                                $record->update(['status' => 'rejected']);
                                Notification::make()
                                    ->title("Solicitud de vacaciones #{$record->id} rechazada.")
                                    ->success()
                                    ->send();
                            }
                        }
                    )
                    ->requiresConfirmation(),
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
            'index' => Pages\ManageVacations::route('/'),
        ];
    }
}
