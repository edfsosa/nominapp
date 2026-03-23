<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\Document;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

/** Gestiona los documentos adjuntos del empleado desde su vista de detalle. */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';
    protected static ?string $title = 'Documentos';
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Documento';
    protected static ?string $pluralModelLabel = 'Documentos';

    /**
     * Define el formulario para subir y editar documentos.
     *
     * @param  Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del documento')
                    ->placeholder('Ej: Contrato laboral, CV, Certificado IPS...')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                FileUpload::make('file_path')
                    ->label('Archivo')
                    ->helperText('Formatos permitidos: PDF, imágenes (JPEG, PNG, GIF) y documentos de Office (Word, Excel, PowerPoint). Tamaño máximo: 10 MB')
                    ->disk('public')
                    ->directory('documents')
                    ->acceptedFileTypes(Document::getAcceptedFileTypes())
                    ->maxSize(10240) // 10 MB
                    ->downloadable()
                    ->previewable()
                    ->openable()
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Define la tabla de documentos con columnas, filtros y acciones.
     *
     * @param  Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre del documento')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->weight('medium'),

                TextColumn::make('file_type')
                    ->label('Tipo')
                    ->state(fn(Document $record): string => $record->file_extension)
                    ->badge()
                    ->color(fn(Document $record): string => $record->file_type_color)
                    ->icon(fn(Document $record): string => $record->file_type_icon)
                    ->sortable(),

                TextColumn::make('file_size')
                    ->label('Tamaño')
                    ->state(fn(Document $record): string => $record->file_size_formatted)
                    ->alignEnd(),

                TextColumn::make('created_at_description')
                    ->label('Fecha de carga')
                    ->description(fn(Document $record): string => $record->created_at_since)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at_description')
                    ->label('Última modificación')
                    ->description(fn(Document $record): string => $record->updated_at_since)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('file_type')
                    ->label('Tipo de archivo')
                    ->options(Document::getFileTypeFilterOptions())
                    ->query(function ($query, $state) {
                        if (!$state['value']) {
                            return $query;
                        }
                        $extensions = explode(',', $state['value']);
                        return $query->where(function ($q) use ($extensions) {
                            foreach ($extensions as $ext) {
                                $q->orWhere('file_path', 'like', '%.' . $ext);
                            }
                        });
                    })
                    ->native(false)
                    ->placeholder('Selecciona un tipo de archivo'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->modalHeading('Subir nuevo documento')
                    ->modalSubmitActionLabel('Subir'),
            ])
            ->actions([
                Action::make('descargar')
                    ->label('Descargar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn(Document $record) => Storage::disk('public')->download(
                        $record->file_path,
                        $record->name . '.' . strtolower($record->file_extension)
                    )),

                EditAction::make(),

                DeleteAction::make()
                    ->before(function ($record) {
                        // Eliminar el archivo físico del storage
                        if (Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function ($records) {
                            // Eliminar los archivos físicos del storage
                            foreach ($records as $record) {
                                if (Storage::disk('public')->exists($record->file_path)) {
                                    Storage::disk('public')->delete($record->file_path);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay documentos')
            ->emptyStateDescription('Comienza subiendo documentos del empleado')
            ->emptyStateIcon('heroicon-o-document-arrow-up');
    }
}
