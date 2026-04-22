<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Warning;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Gestiona las amonestaciones del empleado desde su vista de detalle. */
class WarningsRelationManager extends RelationManager
{
    protected static string $relationship = 'warnings';

    protected static ?string $title = 'Amonestaciones';

    protected static ?string $modelLabel = 'Amonestación';

    protected static ?string $pluralModelLabel = 'Amonestaciones';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario para registrar y editar amonestaciones.
     */
    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Section::make('Información')
                    ->compact()
                    ->columns(2)
                    ->schema([
                        Select::make('type')
                            ->label('Tipo')
                            ->options(Warning::getTypeOptions())
                            ->required(),

                        Select::make('reason')
                            ->label('Motivo')
                            ->options(Warning::getReasonOptions())
                            ->required(),

                        DatePicker::make('issued_at')
                            ->label('Fecha de emisión')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->visibleOn('edit'),

                        Select::make('issued_by_id')
                            ->label('Emitida por')
                            ->relationship('issuedBy', 'name')
                            ->native(false)
                            ->default(fn () => Auth::id())
                            ->required()
                            ->visibleOn('edit'),
                    ]),

                Section::make('Detalle')
                    ->compact()
                    ->schema([
                        Textarea::make('description')
                            ->label('Descripción del hecho')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Textarea::make('notes')
                            ->label('Observaciones adicionales')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Documento Firmado')
                    ->compact()
                    ->collapsed()
                    ->visibleOn('edit')
                    ->schema([
                        FileUpload::make('document_path')
                            ->label('PDF firmado')
                            ->disk('public')
                            ->directory('warnings')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(5120)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Define la tabla de amonestaciones del empleado.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Warning::getTypeLabel($state))
                    ->color(fn ($state) => Warning::getTypeColor($state))
                    ->icon(fn ($state) => Warning::getTypeIcon($state)),

                TextColumn::make('reason')
                    ->label('Motivo')
                    ->formatStateUsing(fn ($state) => Warning::getReasonLabel($state)),

                TextColumn::make('issued_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('issuedBy.name')
                    ->label('Emitida por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Warning::getTypeOptions()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['employee_id'] = $this->getOwnerRecord()->id;
                        $data['issued_at'] = now()->toDateString();
                        $data['issued_by_id'] = Auth::id();

                        return $data;
                    }),
            ])
            ->actions([
                Action::make('download_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('primary')
                    ->url(fn (Warning $record) => route('warnings.pdf', $record))
                    ->openUrlInNewTab(),

                Action::make('download_signed')
                    ->label('Firmado')
                    ->icon('heroicon-o-paper-clip')
                    ->color('success')
                    ->visible(fn (Warning $record) => filled($record->document_path))
                    ->action(fn (Warning $record) => response()->download(
                        Storage::disk('public')->path($record->document_path)
                    )),

                EditAction::make(),

                DeleteAction::make()
                    ->before(function (Warning $record) {
                        if ($record->document_path) {
                            Storage::disk('public')->delete($record->document_path);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin amonestaciones')
            ->emptyStateDescription('Este empleado no tiene amonestaciones registradas.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
