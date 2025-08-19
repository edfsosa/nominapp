<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class FaceDescriptor implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Acepta string JSON o array
        $arr = is_string($value) ? json_decode($value, true) : $value;

        if (!is_array($arr) || count($arr) !== 128) {
            $fail('El descriptor facial debe ser un arreglo de 128 valores.');
            return;
        }
        foreach ($arr as $v) {
            if (!is_numeric($v)) {
                $fail('El descriptor facial contiene valores no numéricos.');
                break;
            }
        }
    }
}
