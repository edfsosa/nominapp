<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Umbral de Ausencia (en minutos)
    |--------------------------------------------------------------------------
    |
    | Tiempo en minutos después de la hora de entrada esperada que debe pasar
    | antes de marcar automáticamente al empleado como ausente.
    |
    | Por defecto: 30 minutos
    |
    */
    'absence_threshold_minutes' => env('ABSENCE_THRESHOLD_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Umbral de Reconocimiento Facial
    |--------------------------------------------------------------------------
    |
    | Distancia euclidiana máxima para considerar un match facial.
    | Menor valor = más estricto (requiere mayor similitud).
    | Rango recomendado: 0.35 - 0.60
    |
    | Por defecto: 0.45
    |
    */
    'face_threshold' => env('FACE_THRESHOLD', 0.45),

    /*
    |--------------------------------------------------------------------------
    | Gap Mínimo de Confianza Facial
    |--------------------------------------------------------------------------
    |
    | Diferencia mínima entre el mejor y segundo mejor candidato para
    | aceptar una identificación. Esto previene confusiones cuando
    | dos rostros son muy similares.
    |
    | Por defecto: 0.1
    |
    */
    'face_min_confidence_gap' => env('FACE_MIN_CONFIDENCE_GAP', 0.1),
];
