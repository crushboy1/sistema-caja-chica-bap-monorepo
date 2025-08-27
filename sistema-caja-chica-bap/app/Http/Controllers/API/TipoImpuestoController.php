<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\TipoImpuesto;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class TipoImpuestoController extends Controller
{
    public function index(Request $request)
    {
        $query = TipoImpuesto::query()->orderBy('nombre');

        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', (bool)$request->activo);
        } else if ($request->query('scope') !== 'management') {
            $query->where('activo', true);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => 'required|string|max:50|unique:tipos_impuesto,nombre',
            'porcentaje' => 'required|numeric|min:0',
            'factor_calculo' => 'required|numeric|min:1',
            'activo' => 'sometimes|boolean',
        ]);

        $tipoImpuesto = TipoImpuesto::create($validatedData);

        return response()->json([
            'message' => 'Tipo de impuesto creado exitosamente.',
            'tipo_impuesto' => $tipoImpuesto
        ], 201);
    }

    public function update(Request $request, TipoImpuesto $tipoImpuesto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'nombre' => ['required', 'string', 'max:50', Rule::unique('tipos_impuesto')->ignore($tipoImpuesto->id_tipo_impuesto, 'id_tipo_impuesto')],
            'porcentaje' => 'required|numeric|min:0',
            'factor_calculo' => 'required|numeric|min:1',
            'activo' => 'sometimes|boolean',
        ]);

        $tipoImpuesto->update($validatedData);

        return response()->json([
            'message' => 'Tipo de impuesto actualizado exitosamente.',
            'tipo_impuesto' => $tipoImpuesto
        ]);
    }

    public function destroy(TipoImpuesto $tipoImpuesto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        if ($tipoImpuesto->gastosProyectados()->count() > 0) {
            return response()->json([
                'message' => 'No se puede desactivar: Este tipo de impuesto está en uso por uno o más gastos proyectados.'
            ], 409);
        }

        $tipoImpuesto->activo = false;
        $tipoImpuesto->save();

        return response()->json(['message' => 'Tipo de impuesto desactivado exitosamente.']);
    }

    public function activate(TipoImpuesto $tipoImpuesto)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $tipoImpuesto->activo = true;
        $tipoImpuesto->save();

        return response()->json([
            'message' => 'Tipo de impuesto activado exitosamente.',
            'tipo_impuesto' => $tipoImpuesto
        ]);
    }
}
