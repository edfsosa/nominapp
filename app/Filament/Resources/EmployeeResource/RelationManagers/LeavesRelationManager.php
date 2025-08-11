<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LeavesRelationManager extends RelationManager
{
    protected static string $relationship = 'leaves';
    protected static ?string $title = 'Permisos';
    protected static ?string $modelLabel = 'Permiso';
    protected static ?string $pluralModelLabel = 'Permisos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Tipo de Permiso')
                    ->options([
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso Maternidad',
                        'paternity_leave' => 'Permiso Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    ])
                    ->native(false)
                    ->searchable()
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('reason')
                    ->label('Descripción/Motivo')
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('document_path')
                    ->label('Documento Comprobante')
                    ->disk('public')
                    ->directory('employee_leaves')
                    ->visibility('public')
                    ->nullable()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240) // 10 MB
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->native(false)
                    ->default('pending')
                    ->hiddenOn('create')
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso Maternidad',
                        'paternity_leave' => 'Permiso Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
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
                    })
                    ->sortable()
                    ->searchable(),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
