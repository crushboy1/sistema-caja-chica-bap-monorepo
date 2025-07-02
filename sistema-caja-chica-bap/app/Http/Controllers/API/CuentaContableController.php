<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CuentaContableController extends Controller
{
    /**
     * Muestra una lista de todas las cuentas contables activas.
     * Usado para poblar los selectores del frontend.
     */
    public function index()
    {
        $cuentas = \App\Models\CuentaContable::where('activo', true)
            ->orderBy('codigo_cuenta')
            ->get();

        // Estandarizamos la respuesta en un objeto JSON
        return response()->json(['cuentas_contables' => $cuentas]);
    }

    /**
     * Almacena una nueva cuenta contable en la base de datos.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'codigo_cuenta' => 'required|string|max:255|unique:cuentas_contables,codigo_cuenta',
            'descripcion' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $cuenta = CuentaContable::create($validatedData);

        return response()->json([
            'message' => 'Cuenta contable creada exitosamente.',
            'cuenta_contable' => $cuenta
        ], 201);
    }

    /**
     * Muestra una cuenta contable específica.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function show(CuentaContable $cuentaContable)
    {
        // Usamos el Route-Model Binding de Laravel para encontrar la cuenta automáticamente.
        return response()->json(['cuenta_contable' => $cuentaContable]);
    }

    /**
     * Actualiza una cuenta contable específica.
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function update(Request $request, CuentaContable $cuentaContable)
    {
        $validatedData = $request->validate([
            'codigo_cuenta' => [
                'required',
                'string',
                'max:255',
                // Asegura que el código sea único, ignorando la cuenta actual que se está editando.
                Rule::unique('cuentas_contables')->ignore($cuentaContable->id),
            ],
            'descripcion' => 'required|string|max:255',
            'activo' => 'boolean',
        ]);

        $cuentaContable->update($validatedData);

        return response()->json([
            'message' => 'Cuenta contable actualizada exitosamente.',
            'cuenta_contable' => $cuentaContable
        ]);
    }

    /**
     * "Elimina" una cuenta contable (la desactiva).
     * Protegido por middleware de rol en el archivo de rutas.
     */
    public function destroy(CuentaContable $cuentaContable)
    {
        $cuentaContable->activo = false;
        $cuentaContable->save();

        return response()->json(['message' => 'Cuenta contable desactivada exitosamente.']);
    }
}