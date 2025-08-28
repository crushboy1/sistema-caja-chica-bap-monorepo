<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CentroCostoController extends Controller
{
    /**
     * Muestra una lista de centros de costo.
     */
    public function index(Request $request)
    {
        $query = CentroCosto::orderBy('codigo', 'asc');

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', (bool)$request->activo);
        } else if ($request->query('scope') !== 'management') {
            $query->where('activo', true);
        }

        $centrosCosto = $query->get();
        return response()->json(['success' => true, 'data' => $centrosCosto]);
    }

    /**
     * Almacena un nuevo centro de costo.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'codigo' => 'required|string|max:20|unique:centros_costo,codigo',
            'descripcion' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);

        $centroCosto = CentroCosto::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Centro de costo creado exitosamente.',
            'data' => $centroCosto
        ], 201);
    }

    /**
     * Actualiza un centro de costo específico.
     */
    public function update(Request $request, CentroCosto $centroCosto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('centros_costo')->ignore($centroCosto->id)],
            'descripcion' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);

        $centroCosto->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Centro de costo actualizado exitosamente.',
            'data' => $centroCosto
        ]);
    }

    /**
     * Desactiva un centro de costo.
     */
    public function destroy(CentroCosto $centroCosto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        if ($centroCosto->area()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede desactivar. El centro de costo está asignado a un área.'
            ], 409);
        }

        $centroCosto->activo = false;
        $centroCosto->save();

        return response()->json(['success' => true, 'message' => 'Centro de costo desactivado exitosamente.']);
    }

    /**
     * Activa un centro de costo.
     */
    public function activate(CentroCosto $centroCosto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['success' => false, 'message' => 'Acción no autorizada.'], 403);
        }

        $centroCosto->activo = true;
        $centroCosto->save();

        return response()->json([
            'success' => true,
            'message' => 'Centro de costo activado exitosamente.',
            'data' => $centroCosto
        ]);
    }
}
