<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Gasto; 

class UniqueComprobante implements Rule
{
    /**
     * Determina si la regla de validación pasa.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        // No se valida si es una declaración jurada
        if ($value['es_declaracion_jurada']) {
            return true;
        }

        // Si faltan campos clave, se asume que pasa (otras reglas se encargarán de la obligatoriedad)
        if (empty($value['tipo_documento']) || empty($value['serie_documento']) || empty($value['correlativo_documento'])) {
            return true;
        }

        // Realiza la consulta para verificar la existencia del comprobante
        return !Gasto::where('tipo_documento', $value['tipo_documento'])
            ->where('serie_documento', $value['serie_documento'])
            ->where('correlativo_documento', $value['correlativo_documento'])
            ->exists();
    }

    /**
     * Obtiene el mensaje de error de validación.
     *
     * @return string
     */
    public function message()
    {
        return 'Este comprobante (serie y correlativo) ya ha sido registrado anteriormente.';
    }
}
