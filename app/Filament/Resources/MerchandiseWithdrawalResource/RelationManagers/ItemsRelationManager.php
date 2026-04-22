<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\RelationManagers;

use App\Models\MerchandiseWithdrawal;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Gestiona los productos (ítems) de un retiro de mercadería.
 *
 * Calcula el subtotal automáticamente (precio × cantidad) y actualiza
 * el total_amount del retiro al crear, editar o eliminar un ítem.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Productos';

    public function isReadOnly(): bool
    {
        /** @var MerchandiseWithdrawal $withdrawal */
        $withdrawal = $this->getOwnerRecord();

        return ! $withdrawal->isPending();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Producto')
                    ->compact()
                    ->icon('heroicon-o-cube')
                    ->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->placeholder('Ej: PROD-001')
                            ->maxLength(50),

                        TextInput::make('name')
                            ->label('Nombre del Producto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Precio')
                    ->compact()
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('price')
                            ->label('Precio Unitario')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('Gs.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('subtotal', (int) $get('quantity') * (float) $get('price'))
                            ),

                        TextInput::make('quantity')
                            ->label('Cantidad')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->default(1)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('subtotal', (int) $get('quantity') * (float) $get('price'))
                            ),

                        TextInput::make('subtotal')
                            ->label('Subtotal')
                            ->numeric()
                            ->prefix('Gs.')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->placeholder('-')
                    ->toggleable(),

                TextColumn::make('name')
                    ->label('Producto')
                    ->wrap()
                    ->searchable(),

                TextColumn::make('price')
                    ->label('Precio Unit.')
                    ->money('PYG', locale: 'es_PY'),

                TextColumn::make('quantity')
                    ->label('Cant.')
                    ->alignCenter(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->money('PYG', locale: 'es_PY')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Producto')
                    ->modalHeading('Agregar Producto')
                    ->modalSubmitActionLabel('Agregar')
                    ->after(fn () => $this->recalculateTotal()),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar Producto')
                    ->after(fn () => $this->recalculateTotal()),
                DeleteAction::make()
                    ->modalHeading('Eliminar Producto')
                    ->modalDescription('¿Está seguro? Se eliminará el producto del retiro.')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->after(fn () => $this->recalculateTotal()),
            ])
            ->paginated(false)
            ->emptyStateHeading('Sin productos')
            ->emptyStateDescription('Agregue los productos del retiro antes de aprobarlo.')
            ->emptyStateIcon('heroicon-o-cube');
    }

    /**
     * Recalcula total_amount del retiro sumando los subtotales de todos sus ítems.
     */
    private function recalculateTotal(): void
    {
        /** @var MerchandiseWithdrawal $withdrawal */
        $withdrawal = $this->getOwnerRecord();
        $total = $withdrawal->items()->sum('subtotal');
        $withdrawal->update(['total_amount' => $total]);
    }
}
