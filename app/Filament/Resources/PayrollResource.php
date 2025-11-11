<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollResource\Pages;
use App\Filament\Resources\PayrollResource\RelationManagers;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PayrollResource extends Resource
{
    protected static ?string $model = Payroll::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Recibos';
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $slug = 'recibos';
    protected static ?string $pluralModelLabel = 'Recibos';
    protected static ?string $modelLabel = 'Recibo';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                Select::make('payroll_period_id')
                    ->label('Periodo')
                    ->relationship('period', 'start_date')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required(),
                TextInput::make('gross_salary')
                    ->label('Salario bruto')
                    ->numeric()
                    ->required(),
                TextInput::make('total_deductions')
                    ->label('Deducciones')
                    ->numeric()
                    ->required(),
                TextInput::make('total_perceptions')
                    ->label('Percepciones')
                    ->numeric()
                    ->required(),
                TextInput::make('net_salary')
                    ->label('Salario neto')
                    ->numeric()
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
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period.name')
                    ->label('Periodo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('gross_salary')
                    ->label('Salario bruto')
                    ->money('PYG', true),
                TextColumn::make('total_deductions')
                    ->label('Deducciones')
                    ->money('PYG', true),
                TextColumn::make('total_perceptions')
                    ->label('Percepciones')
                    ->money('PYG', true),
                TextColumn::make('net_salary')
                    ->label('Salario neto')
                    ->money('PYG', true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('pdf')
                    ->label('Generar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->url(fn (Payroll $record) => route('payrolls.pdf', ['payroll' => $record->id]))
                    ->openUrlInNewTab(),
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
            RelationManagers\ItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrolls::route('/'),
            'create' => Pages\CreatePayroll::route('/create'),
            'edit' => Pages\EditPayroll::route('/{record}/edit'),
        ];
    }
}
