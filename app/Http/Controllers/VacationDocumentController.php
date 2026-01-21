<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VacationDocumentController extends Controller
{
    /**
     * Descarga un documento de vacaciones.
     */
    public function download(string $filename): BinaryFileResponse
    {
        $path = storage_path('app/public/temp/' . $filename);

        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        // Obtener nombre limpio (sin UUID)
        $cleanFilename = preg_replace('/^[a-f0-9-]+_/', '', $filename);

        return response()->download($path, $cleanFilename)->deleteFileAfterSend(true);
    }
}
