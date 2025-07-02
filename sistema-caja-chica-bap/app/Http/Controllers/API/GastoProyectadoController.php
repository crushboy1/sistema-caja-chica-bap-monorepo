<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GastoProyectado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GastoProyectadoController extends Controller
{
    /**
     * Muestra una lista de todos los gastos proyectados activos.
     * Usado para poblar los selectores del frontend.
     */
    public function index()
    {
        $gastosProyectados = GastoProyectado::where('activo', true)
            ->with('cuentaContable:id,codigo_cuenta,descripcion')
            ->orderBy('descripcion')
            ->get();

        return response()->json(['gastos_proyectados' => $gastosProyectados]);
    }

    /**
     * Almacena un nuevo gasto proyectado.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'descripcion' => 'required|string|max:255|unique:gastos_proyectados,descripcion',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'activa' => 'boolean',
        ]);

        $gastoProyectado = GastoProyectado::create($validatedData);
        $gastoProyectado->load('cuentaContable');

        return response()->json([
            'message' => 'Gasto proyectado creado exitosamente.',
            'gasto_proyectado' => $gastoProyectado
        ], 201);
    }

    /**
     * Actualiza un gasto proyectado específico.
     */
    public function update(Request $request, GastoProyectado $gastoProyectado)
    {
        $validatedData = $request->validate([
            'descripcion' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gastos_proyectados')->ignore($gastoProyectado->id_gasto_proyectado, 'id_gasto_proyectado'),
            ],
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'activa' => 'boolean',
        ]);

        $gastoProyectado->update($validatedData);
        $gastoProyectado->load('cuentaContable');

        return response()->json([
            'message' => 'Gasto proyectado actualizado exitosamente.',
            'gasto_proyectado' => $gastoProyectado
        ]);
    }

    /**
     * Desactiva un gasto proyectado.
     */
    public function destroy(GastoProyectado $gastoProyectado)
    {
        $gastoProyectado->activa = false;
        $gastoProyectado->save();

        return response()->json(['message' => 'Gasto proyectado desactivado exitosamente.']);
    }
}
