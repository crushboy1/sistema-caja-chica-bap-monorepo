<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Area; // Asegúrate de importar el modelo Area
use Illuminate\Support\Facades\Auth; // Para verificar roles si fuera necesario

class AreaController extends Controller
{
    /**
     * Muestra una lista de todas las áreas.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            // No se necesitan filtros de rol aquí, ya que las áreas son datos generales
            // que cualquier usuario autenticado que acceda a este módulo debería ver.
            $areas = Area::all(); // Obtener todas las áreas

            return response()->json([
                'message' => 'Áreas obtenidas exitosamente.',
                'areas' => $areas,
            ], 200);

        } catch (\Exception $e) {
            // Manejo de cualquier excepción que pueda ocurrir
            return response()->json([
                'message' => 'Ocurrió un error al obtener las áreas.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Puedes añadir otros métodos (store, show, update, destroy) si son necesarios
    // para la gestión de áreas a través de la API en el futuro.
}

