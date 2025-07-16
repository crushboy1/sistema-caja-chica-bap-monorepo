<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CuentaContable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CuentaContableController extends Controller
{
    /**
     * Muestra una lista de cuentas contables.
     * Acepta un parámetro 'scope' para diferenciar entre la vista de administración y los selectores.
     */
    public function index(Request $request)
    {
        $query = CuentaContable::orderBy('codigo_cuenta');

        if ($request->query('scope') === 'management') {
            // Para el panel de administración, mostrar todas las cuentas
        } else {
            // Por defecto, para otros usos (ej. selectores), solo las activas
            $query->where('activo', true);
        }

        $cuentas = $query->get();

        return response()->json(['cuentas_contables' => $cuentas]);
    }

    /**
     * Almacena una nueva cuenta contable en la base de datos.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $validatedData = $request->validate([
            'codigo_cuenta' => 'required|string|max:255|unique:cuentas_contables,codigo_cuenta',
            'descripcion' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);

        $cuenta = CuentaContable::create($validatedData);

        return response()->json([
            'message' => 'Cuenta contable creada exitosamente.',
            'cuenta_contable' => $cuenta
        ], 201);
    }

    /**
     * Muestra una cuenta contable específica.
     */
    public function show(CuentaContable $cuentaContable)
    {
        return response()->json(['cuentas_contables' => $cuentaContable]);
    }

    /**
     * Actualiza una cuenta contable específica.
     * Se restringe la actualización para que el 'codigo_cuenta' sea inmutable.
     */
    public function update(Request $request, CuentaContable $cuentaContable)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $validatedData = $request->validate([
            // El código de cuenta no debe ser editable una vez creado.
            'descripcion' => 'required|string|max:255',
            'activo' => 'sometimes|boolean',
        ]);

        $cuentaContable->update($validatedData);

        return response()->json([
            'message' => 'Cuenta contable actualizada exitosamente.',
            'cuenta_contable' => $cuentaContable
        ]);
    }

    /**
     * "Elimina" una cuenta contable (la desactiva) tras validar que no esté en uso.
     */
    public function destroy(CuentaContable $cuentaContable)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        //  Verificar si la cuenta está siendo usada por algún Gasto Proyectado ACTIVO.
        $gastosProyectadosActivos = $cuentaContable->gastosProyectados()->where('activo', true)->count();

        if ($gastosProyectadosActivos > 0) {
            return response()->json([
                'message' => "No se puede desactivar: La cuenta está en uso por {$gastosProyectadosActivos} gasto(s) proyectado(s) activo(s)."
            ], 409);
        }

        // Si no está en uso, se puede desactivar.
        $cuentaContable->activo = false;
        $cuentaContable->save();

        return response()->json(['message' => 'Cuenta contable desactivada exitosamente.']);
    }

    /**
     * Activa una cuenta contable que fue desactivada.
     */
    public function activate(CuentaContable $cuentaContable)
    {
        $cuentaContable->activo = true;
        $cuentaContable->save();

        return response()->json([
            'message' => 'Cuenta contable activada exitosamente.',
            'cuenta_contable' => $cuentaContable
        ]);
    }
}
