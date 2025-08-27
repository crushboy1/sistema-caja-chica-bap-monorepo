<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ClasificacionBienServicio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ClasificacionBienServicioController extends Controller
{
    /**
     * Muestra una lista de clasificaciones.
     * Soporta filtrado y un scope para management.
     */
    public function index(Request $request)
    {
        $query = ClasificacionBienServicio::query()->orderBy('codigo');

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', (bool)$request->activo);
        } else if ($request->query('scope') !== 'management') {
            $query->where('activo', true); 
        }

        $clasificaciones = $query->get();
        return response()->json($clasificaciones);
    }

    /**
     * Almacena una nueva clasificación.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:255|unique:clasificaciones_bien_servicio,nombre',
            'codigo' => 'nullable|string|max:10|unique:clasificaciones_bien_servicio,codigo',
            'activo' => 'sometimes|boolean',
        ]);

        $clasificacion = ClasificacionBienServicio::create($validatedData);

        return response()->json([
            'message' => 'Clasificación creada exitosamente.',
            'clasificacion' => $clasificacion
        ], 201);
    }

    /**
     * Actualiza una clasificación específica.
     */
    public function update(Request $request, ClasificacionBienServicio $clasificacion)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('clasificaciones_bien_servicio')->ignore($clasificacion->id_clasificacion_bien_servicio, 'id_clasificacion_bien_servicio')],
            'codigo' => ['nullable', 'string', 'max:10', Rule::unique('clasificaciones_bien_servicio')->ignore($clasificacion->id_clasificacion_bien_servicio, 'id_clasificacion_bien_servicio')],
            'activo' => 'sometimes|boolean',
        ]);

        $clasificacion->update($validatedData);

        return response()->json([
            'message' => 'Clasificación actualizada exitosamente.',
            'clasificacion' => $clasificacion
        ]);
    }

    /**
     * Desactiva una clasificación.
     */
    public function destroy(ClasificacionBienServicio $clasificacion)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        // Validación: Verificar si la clasificación está en uso por algún Gasto Proyectado.
        if ($clasificacion->gastosProyectados()->count() > 0) {
            return response()->json([
                'message' => 'No se puede desactivar: Esta clasificación está siendo utilizada por uno o más gastos proyectados.'
            ], 409); // 409 Conflict
        }

        $clasificacion->activo = false;
        $clasificacion->save();

        return response()->json(['message' => 'Clasificación desactivada exitosamente.']);
    }

    /**
     * Activa una clasificación.
     */
    public function activate(ClasificacionBienServicio $clasificacion)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $clasificacion->activo = true;
        $clasificacion->save();

        return response()->json([
            'message' => 'Clasificación activada exitosamente.',
            'clasificacion' => $clasificacion
        ]);
    }
}
