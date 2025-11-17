<?php

namespace App\Filament\Resources;

use Filament\Infolists\Infolist;
use App\Filament\Resources\PayrollPeriodResource\Pages;
use App\Filament\Resources\PayrollPeriodResource\RelationManagers\PayrollsRelationManager;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Periodos';
    protected static ?string $slug = 'periodos';
    protected static ?string $pluralModelLabel = 'periodos';
    protected static ?string $modelLabel = 'periodo';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->maxLength(255)
                    ->nullable(),
                Select::make('frequency')
                    ->label('Frecuencia')
                    ->options([
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                    ])
                    ->native(false)
                    ->required(),
                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->required(),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'processing' => 'En proceso',
                        'closed' => 'Cerrado',
                    ])
                    ->native(false)
                    ->default('draft')
                    ->required(),
                DateTimePicker::make('closed_at')
                    ->label('Cerrado en')
                    ->displayFormat('d/m/Y H:i')
                    ->closeOnDateSelection()
                    ->native(false)
                    ->nullable(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(65535)
                    ->rows(3)
                    ->columnSpanFull()
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->label('Fecha de inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->label('Fecha de fin')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'processing' => 'warning',
                        'closed' => 'success',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'processing' => 'En proceso',
                        'closed' => 'Cerrado',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('closed_at')
                    ->label('Cerrado en')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado en')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado en')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'processing' => 'En proceso',
                        'closed' => 'Cerrado',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('generatePayrolls')
                    ->label('Generar recibos')
                    ->icon('heroicon-o-document-text')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function (PayrollPeriod $record, PayrollService $payrollService) {
                        $count = $payrollService->generateForPeriod($record);

                        if ($count > 0) {
                            // Cambiar estado del periodo si lo necesitás
                            $record->update([
                                'status' => 'processing', // o 'closed' si querés marcarlo como cerrado
                                'closed_at' => now(),
                            ]);

                            Notification::make()
                                ->title("Se generaron {$count} recibos.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('No se generaron recibos.')
                                ->body('Es posible que ya hayan sido generados o que no haya empleados válidos.')
                                ->danger()
                                ->send();
                        }
                    })
                    ->visible(fn(PayrollPeriod $record) => $record->status === 'draft'),
                Tables\Actions\Action::make('closePeriod')
                    ->label('Cerrar periodo')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (PayrollPeriod $record) {
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('El periodo ha sido cerrado.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn(PayrollPeriod $record) => $record->status === 'processing'),
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
            'index' => Pages\ListPayrollPeriods::route('/'),
            'create' => Pages\CreatePayrollPeriod::route('/create'),
            'view' => Pages\ViewPayrollPeriod::route('/{record}'),
            'edit' => Pages\EditPayrollPeriod::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Fieldset::make('Detalles del Periodo de Nómina')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre'),
                        TextEntry::make('frequency')
                            ->label('Frecuencia')
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'monthly' => 'Mensual',
                                'biweekly' => 'Quincenal',
                                'weekly' => 'Semanal',
                                default => $state,
                            }),
                        TextEntry::make('start_date')
                            ->label('Fecha de inicio')
                            ->date('d/m/Y'),
                        TextEntry::make('end_date')
                            ->label('Fecha de fin')
                            ->date('d/m/Y'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'draft' => 'Borrador',
                                'processing' => 'En proceso',
                                'closed' => 'Cerrado',
                                default => $state,
                            }),
                        TextEntry::make('closed_at')
                            ->label('Cerrado en')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
