<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VacationDocumentController extends Controller
{
    /**
     * Muestra o descarga un documento de vacaciones.
     * PDFs se muestran inline, ZIPs se descargan.
     */
    public function download(string $filename): BinaryFileResponse
    {
        $path = storage_path('app/public/temp/' . $filename);

        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }

        // Obtener nombre limpio (sin UUID)
        $cleanFilename = preg_replace('/^[a-f0-9-]+_/', '', $filename);

        // Si es ZIP, forzar descarga; si es PDF, mostrar inline
        if (str_ends_with($filename, '.zip')) {
            return response()->download($path, $cleanFilename)->deleteFileAfterSend(true);
        }

        // Para PDFs, mostrar inline en el navegador
        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $cleanFilename . '"',
        ])->deleteFileAfterSend(true);
    }
}
