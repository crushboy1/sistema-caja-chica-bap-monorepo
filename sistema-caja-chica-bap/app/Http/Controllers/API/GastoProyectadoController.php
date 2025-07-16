<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GastoProyectado;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class GastoProyectadoController extends Controller
{
    /**
     * Muestra una lista de gastos proyectados.
     * Si se pasa el parámetro '?scope=management', devuelve todos.
     * De lo contrario, devuelve solo los activos.
     */
    public function index(Request $request)
    {
        $query = GastoProyectado::with('cuentaContable:id,codigo_cuenta,descripcion')
            ->orderBy('descripcion');

        // Si el scope es para administración, mostrar todos (activos e inactivos)
        if ($request->query('scope') === 'management') {
            // No se aplica filtro de 'activo'
        } else {
            // Por defecto, para los selectores del frontend, solo mostrar los activos
            $query->where('activo', true);
        }

        $gastosProyectados = $query->get();

        return response()->json(['gastos_proyectados' => $gastosProyectados]);
    }

    /**
     * Almacena un nuevo gasto proyectado.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $validatedData = $request->validate([
            'descripcion' => 'required|string|max:255|unique:gastos_proyectados,descripcion',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            // [CORREGIDO] Se usa 'activo' en lugar de 'activa' para coherencia.
            'activo' => 'sometimes|boolean',
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
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $validatedData = $request->validate([
            'descripcion' => ['required', 'string', 'max:255', Rule::unique('gastos_proyectados')->ignore($gastoProyectado)],
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'activo' => 'sometimes|boolean',
        ]);

        $gastoProyectado->update($validatedData);

        return response()->json([
            'message' => 'Gasto proyectado actualizado exitosamente.',
            'gasto_proyectado' => $gastoProyectado->load('cuentaContable')
        ]);
    }

    /**
     * Desactiva un gasto proyectado (Soft Delete).
     * El método se llama 'destroy' para ser compatible con apiResource, pero solo desactiva.
     */
    public function destroy(GastoProyectado $gastoProyectado)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        // Validación 1: Verificar si el gasto está en solicitudes en proceso.
        $solicitudesEnProceso = $gastoProyectado->solicitudesFondos()
            ->whereNotIn('estado', ['Aprobada', 'Rechazada Final'])
            ->count();

        if ($solicitudesEnProceso > 0) {
            return response()->json(['message' => 'No se puede desactivar: Este gasto forma parte de solicitudes que aún están en proceso.'], 409); // 409 Conflict
        }

        // Validación 2: Verificar si el gasto está en fondos de caja chica activos.
        $fondosActivosAsociados = $gastoProyectado->solicitudesFondos()
            ->whereHas('fondoEfectivo', function ($query) {
                $query->where('estado', 'Activo');
            })->count();

        if ($fondosActivosAsociados > 0) {
            return response()->json(['message' => 'No se puede desactivar: Este gasto forma parte de fondos de caja chica que están actualmente activos.'], 409);
        }

        // Si pasa las validaciones, se desactiva.
        $gastoProyectado->activo = false;
        $gastoProyectado->save();

        return response()->json(['message' => 'Gasto proyectado desactivado exitosamente.']);
    }

    /**
     * Activa un gasto proyectado que fue desactivado.
     */
    public function activate(GastoProyectado $gastoProyectado)
    {
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $gastoProyectado->activo = true;
        $gastoProyectado->save();

        return response()->json([
            'message' => 'Gasto proyectado activado exitosamente.',
            'gasto_proyectado' => $gastoProyectado->load('cuentaContable')
        ]);
    }
}
