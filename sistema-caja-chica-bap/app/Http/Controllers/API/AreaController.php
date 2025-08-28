<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class AreaController extends Controller
{
    /**
     * Muestra una lista de áreas, incluyendo la información de su centro de costo.
     */
    public function index(Request $request)
    {
        // Eager load the centroCosto relationship to avoid N+1 query problems
        $query = Area::with('centroCosto:id,codigo,descripcion')->orderBy('name', 'asc');

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', (bool)$request->activo);
        } else if ($request->query('scope') !== 'management') {
            $query->where('activo', true);
        }

        $areas = $query->get();
        return response()->json(['success' => true, 'data' => $areas]);
    }

    /**
     * Almacena una nueva área.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:areas,name',
            'description' => 'nullable|string|max:255',
            'acronym' => 'nullable|string|max:10',
            'centro_costo_id' => [
                'nullable',
                'integer',
                Rule::exists('centros_costo', 'id')->where('activo', true),
                Rule::unique('areas', 'centro_costo_id')
            ],
            'activo' => 'sometimes|boolean',
        ]);

        $area = Area::create($validatedData);
        $area->load('centroCosto:id,codigo,descripcion');

        return response()->json([
            'success' => true,
            'message' => 'Área creada exitosamente.',
            'data' => $area
        ], 201);
    }

    /**
     * Actualiza un área específica.
     */
    public function update(Request $request, Area $area)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('areas')->ignore($area->id)],
            'description' => 'nullable|string|max:255',
            'acronym' => 'nullable|string|max:10',
            'centro_costo_id' => [
                'nullable',
                'integer',
                Rule::exists('centros_costo', 'id')->where('activo', true),
                Rule::unique('areas', 'centro_costo_id')->ignore($area->id)
            ],
            'activo' => 'sometimes|boolean',
        ]);

        $area->update($validatedData);
        $area->load('centroCosto:id,codigo,descripcion');

        return response()->json([
            'success' => true,
            'message' => 'Área actualizada exitosamente.',
            'data' => $area
        ]);
    }

    /**
     * Desactiva un área.
     */
    public function destroy(Area $area)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        if ($area->users()->exists() || $area->proyectos()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar el área porque tiene usuarios o proyectos asociados.'
            ], 409);
        }

        $area->activo = false;
        $area->save();

        return response()->json(['success' => true, 'message' => 'Área desactivada exitosamente.']);
    }

    /**
     * Activa un área.
     */
    public function activate(Area $area)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $area->activo = true;
        $area->save();

        return response()->json([
            'success' => true,
            'message' => 'Área activada exitosamente.',
            'data' => $area
        ]);
    }
}
