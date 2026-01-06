<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    /** @use HasFactory<\Database\Factories\DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'name',
        'file_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Obtiene la URL pública del archivo
     */
    public function getFileUrlAttribute(): string
    {
        return asset('storage/' . $this->file_path);
    }

    /**
     * Obtiene la extensión del archivo
     */
    public function getFileExtensionAttribute(): string
    {
        return strtoupper(pathinfo($this->file_path, PATHINFO_EXTENSION));
    }

    /**
     * Obtiene el color del badge según el tipo de archivo
     */
    public function getFileTypeColorAttribute(): string
    {
        return match (strtolower($this->file_extension)) {
            'pdf' => 'danger',
            'jpg', 'jpeg', 'png', 'gif' => 'success',
            'docx', 'doc' => 'info',
            'xlsx', 'xls' => 'warning',
            'pptx', 'ppt' => 'primary',
            default => 'gray',
        };
    }

    /**
     * Obtiene el ícono según el tipo de archivo
     */
    public function getFileTypeIconAttribute(): string
    {
        return match (strtolower($this->file_extension)) {
            'pdf' => 'heroicon-o-document-text',
            'jpg', 'jpeg', 'png', 'gif' => 'heroicon-o-photo',
            'docx', 'doc' => 'heroicon-o-document',
            'xlsx', 'xls' => 'heroicon-o-table-cells',
            'pptx', 'ppt' => 'heroicon-o-presentation-chart-bar',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Obtiene el tamaño del archivo formateado
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $path = storage_path('app/public/' . $this->file_path);

        if (!file_exists($path)) {
            return 'N/A';
        }

        $bytes = filesize($path);

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Obtiene la fecha de creación formateada
     */
    public function getCreatedAtDescriptionAttribute(): string
    {
        return $this->created_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha de creación en formato "hace X tiempo"
     */
    public function getCreatedAtSinceAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Obtiene la fecha de actualización formateada
     */    public function getUpdatedAtDescriptionAttribute(): string
    {
        return $this->updated_at->format('d/m/Y H:i');
    }

    /**
     * Obtiene la fecha de actualización en formato "hace X tiempo"
     */    public function getUpdatedAtSinceAttribute(): string
    {
        return $this->updated_at->diffForHumans();
    }

    /**
     * Obtiene los tipos de archivo aceptados
     */
    public static function getAcceptedFileTypes(): array
    {
        return [
            'application/pdf', // PDF
            'image/jpeg', // Imágenes JPEG
            'image/jpg', // Imágenes JPG
            'image/png', // Imágenes PNG
            'image/gif', // Imágenes GIF
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',  // Word (.docx)
            'application/msword', // Word (.doc)
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // Excel (.xlsx)
            'application/vnd.ms-excel', // Excel (.xls)
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // PowerPoint (.pptx)
            'application/vnd.ms-powerpoint', // PowerPoint (.ppt)
        ];
    }

    /**
     * Obtiene las opciones de filtro por tipo de archivo
     */
    public static function getFileTypeFilterOptions(): array
    {
        return [
            'pdf' => 'PDF',
            'jpg,jpeg,png,gif' => 'Imágenes',
            'docx,doc' => 'Word',
            'xlsx,xls' => 'Excel',
            'pptx,ppt' => 'PowerPoint',
        ];
    }
}
