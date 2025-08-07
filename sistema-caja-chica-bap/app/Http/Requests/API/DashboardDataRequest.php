<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class DashboardDataRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     */
    public function authorize(): bool
    {
        // Aquí podemos añadir lógica de permisos si es necesario. Por ahora, true.
        return true;
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
