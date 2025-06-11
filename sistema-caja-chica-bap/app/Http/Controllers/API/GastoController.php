<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\FondoEfectivo;
use App\Models\HistorialAprobacionGasto;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * GastoController se encarga de todo el ciclo de vida de las declaraciones de gastos.
 * Esta versión refactorizada utiliza endpoints de acción específicos (approve, observe, etc.)
 * para una API más clara, segura y mantenible, eliminando el "Fat Controller".
 */
class GastoController extends Controller
{
    /**
     * Muestra una lista de gastos.
     * La lógica de autorización determina qué gastos puede ver el usuario según su rol.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Gasto::with([
            'registrador.role',
            'registrador.area:id,name',
            'jefeAprobador:id,name,last_name',
            'cuentaContable',
            'fondoEfectivo:id_fondo,codigo_fondo'
        ]);

        // --- LÓGICA DE VISUALIZACIÓN POR ROL Y SCOPE ---

        // CASO 1: Es un Jefe de Área en su panel de "Aprobaciones"
        if ($request->input('scope') === 'aprobaciones' && $user->hasRole('jefe_area')) {
            $query->where(function ($q) use ($user) {
                // Condición A: Gastos PENDIENTES de otros miembros de su área (para aprobar)
                $q->where('estado', 'Pendiente de Aprobación Jefatura')
                    ->where('id_registrador', '!=', $user->id)
                    ->whereHas('registrador', function ($subQ) use ($user) {
                        $subQ->where('area_id', $user->area_id);
                    });
                // Condición B: O, sus propios gastos ya APROBADOS (para trazabilidad)
                $q->orWhere(function ($orQ) use ($user) {
                    $orQ->where('estado', 'Aprobado por Jefatura')
                        ->where('id_registrador', $user->id);
                });
            });
        }
        // CASO 2: Es cualquier otro rol o vista (Trazabilidad, Auditoría, etc.)
        else {
            if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                // Admin ve todo
            } elseif ($user->hasRole('jefe_area')) {
                // Jefe de área ve todo lo de su área
                $query->whereHas('registrador', function ($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });
            } else {
                // Colaborador solo ve lo suyo
                $query->where('id_registrador', $user->id);
            }

            // Aplicar filtro de estado genérico solo en este caso
            if ($request->filled('estado') && $request->estado !== 'Todos') {
                $query->where('estado', $request->estado);
            }
        }

        // Aplicar filtros de búsqueda adicionales de la request.
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('codigo_gasto')) {
            $query->where('codigo_gasto', 'like', '%' . $request->codigo_gasto . '%');
        }
        if ($request->filled('registrador_name')) {
            $searchTerm = strtolower($request->registrador_name);
            $query->whereHas('registrador', function ($q) use ($searchTerm) {
                $q->where(DB::raw("CONCAT(LOWER(name), ' ', LOWER(last_name))"), 'like', '%' . $searchTerm . '%');
            });
        }

        $gastos = $query->orderBy('created_at', 'desc')->get();
        return response()->json($gastos);
    }

    /**
     * Paso 1: Almacena un nuevo gasto en la base de datos.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_fondo_efectivo' => 'required|exists:fondo_efectivo,id_fondo',
            'fecha_documento' => 'required|date',
            'tipo_documento' => 'required_if:es_declaracion_jurada,false|string|max:100',
            'serie_documento' => 'nullable|string|max:20',
            'correlativo_documento' => 'nullable|string|max:50',
            'monto_total' => 'required|numeric|min:0.01',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'glosa' => 'required|string|max:1000',
            'evidencia' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', 
            'es_declaracion_jurada' => 'required|boolean',
            'comentario' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
        // Validación de Saldo: Se realiza para todos los roles al momento de registrar.
        if ($fondo->monto_disponible < $validatedData['monto_total']) {
            throw ValidationException::withMessages([
                'monto_total' => 'El monto del gasto excede el saldo disponible del fondo (S/. ' . number_format($fondo->monto_disponible, 2) . ').'
            ]);
        }

        return DB::transaction(function () use ($request, $validatedData, $user, $fondo) {
            $path = $request->file('evidencia')->store('evidencias_gastos', 'public');

            $estadoInicial = 'Pendiente de Aprobación Jefatura';
            $idJefeAprobador = null;
            $historialComentario = 'Gasto registrado por colaborador.';

            // --- LÓGICA DE AUTO-APROBACIÓN PARA JEFE DE ÁREA ---
            if ($user->hasRole('jefe_area')) {
                $estadoInicial = 'Aprobado por Jefatura';
                $idJefeAprobador = $user->id;
                $historialComentario = 'Gasto registrado y auto-aprobado por Jefe de Área.';

                // Se descuenta el saldo del fondo EN TIEMPO REAL.
                $fondo->decrement('monto_disponible', $validatedData['monto_total']);
            }

            $gasto = Gasto::create([
                'id_fondo_efectivo' => $validatedData['id_fondo_efectivo'],
                'id_registrador' => $user->id,
                'id_jefe_aprobador' => $idJefeAprobador,
                'fecha_documento' => $validatedData['fecha_documento'],
                'tipo_documento' => $validatedData['es_declaracion_jurada'] ? 'Declaración Jurada' : $validatedData['tipo_documento'],
                'serie_documento' => $validatedData['es_declaracion_jurada'] ? null : $validatedData['serie_documento'],
                'correlativo_documento' => $validatedData['es_declaracion_jurada'] ? null : $validatedData['correlativo_documento'],
                'monto_total' => $validatedData['monto_total'],
                'id_cuenta_contable' => $validatedData['id_cuenta_contable'],
                'glosa' => $validatedData['glosa'],
                'ruta_evidencia' => $path,
                'es_declaracion_jurada' => $validatedData['es_declaracion_jurada'],
                'comentario' => $validatedData['comentario'],
                'estado' => $estadoInicial,
            ]);

            $this->registrarHistorial($gasto, 'Creado', $gasto->estado, $user->id, 'Gasto registrado por colaborador.');

            return response()->json(['message' => 'Gasto registrado exitosamente.', 'gasto' => $gasto->load('registrador')], 201);
        });
    }

    /**
     * Paso 2: Aprueba un gasto. (Acción del Jefe de Área)
     */
    public function approve(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        // 1. Autorización: ¿Quién puede aprobar?
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para aprobar este gasto.'], 403);
        }

        // 2. Validación de Estado: ¿Se puede aprobar este gasto?
        if ($gasto->estado !== 'Pendiente de Aprobación Jefatura') {
            return response()->json(['message' => 'El gasto no puede ser aprobado porque no está pendiente.'], 409); // 409 Conflict
        }

        // 3. Validación de Negocio: ¿Hay saldo suficiente?
        if ($gasto->fondoEfectivo->monto_disponible < $gasto->monto_total) {
            return response()->json(['message' => 'El fondo no tiene saldo suficiente para cubrir este gasto.'], 409);
        }

        // 4. Ejecución
        DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Aprobado por Jefatura';
            $gasto->id_jefe_aprobador = $user->id;
            $gasto->save();

            $gasto->fondoEfectivo->decrement('monto_disponible', $gasto->monto_total);

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto aprobado por Jefe de Área.'));
        });

        return response()->json(['message' => 'Gasto aprobado exitosamente.', 'gasto' => $gasto]);
    }
    public function rejectByJefe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);
        $comentario = $request->input('comentario');

        // 1. Autorización
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para rechazar este gasto.'], 403);
        }

        // 2. Validación de Estado
        if ($gasto->estado !== 'Pendiente de Aprobación Jefatura') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que estén pendientes.'], 409);
        }

        // 3. Ejecución
        $estadoAnterior = $gasto->estado;
        $gasto->estado = 'Rechazado';
        $gasto->motivo_rechazo = $comentario;
        $gasto->save();

        // No se revierte dinero porque nunca se descontó del fondo.
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $comentario);

        return response()->json(['message' => 'Gasto rechazado exitosamente.', 'gasto' => $gasto]);
    }

    /**
     * Paso 3: Observa un gasto. (Acción de Administración)
     */
    public function observe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);

        // 1. Autorización
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para observar gastos.'], 403);
        }

        // 2. Validación de Estado
        if ($gasto->estado !== 'Aprobado por Jefatura') {
            return response()->json(['message' => 'Solo se pueden observar gastos previamente aprobados por la jefatura.'], 409);
        }

        // 3. Ejecución
        DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Observado por Administración';
            $gasto->motivo_observacion_adm = $request->comentario;
            $gasto->save();

            // Revertir el saldo al fondo
            $gasto->fondoEfectivo->increment('monto_disponible', $gasto->monto_total);

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->comentario);
        });

        return response()->json(['message' => 'Gasto observado y devuelto al flujo.', 'gasto' => $gasto]);
    }

    /**
     * Paso 4 (Parte 1): Devuelve un gasto observado al colaborador. (Acción del Jefe de Área)
     */
    public function returnToCollaborator(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        // 1. Autorización
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para devolver este gasto.'], 403);
        }

        // 2. Validación de Estado
        if ($gasto->estado !== 'Observado por Administración') {
            return response()->json(['message' => 'Solo se pueden devolver gastos que han sido observados por administración.'], 409);
        }

        // 3. Ejecución
        $estadoAnterior = $gasto->estado;
        $gasto->estado = 'Devuelto para Corrección';
        $gasto->save();
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Devuelto al colaborador para corrección.'));

        return response()->json(['message' => 'Gasto devuelto al colaborador para su corrección.', 'gasto' => $gasto]);
    }

    /**
     * Paso 4 (Parte 2): El colaborador corrige y reenvía el gasto.
     */
    public function resubmit(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        // 1. Autorización: Solo el registrador original puede corregir su gasto.
        if ($user->id !== $gasto->id_registrador) {
            return response()->json(['message' => 'No tienes permiso para corregir este gasto.'], 403);
        }

        // 2. Validación de Estado
        if ($gasto->estado !== 'Devuelto para Corrección') {
            return response()->json(['message' => 'Este gasto no está en estado de corrección.'], 409);
        }

        // 3. Validación de Datos (similar a store, pero la evidencia no es siempre requerida)
        $validatedData = $request->validate([
            // ... aquí puedes añadir validaciones para los campos que el usuario puede corregir
            'monto_total' => 'required|numeric|min:0.01',
            'glosa' => 'required|string|max:1000',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', // Evidencia es opcional
            // ... otros campos que permitas editar ...
        ]);

        // 4. Ejecución
        DB::transaction(function () use ($gasto, $user, $request, $validatedData) {
            $estadoAnterior = $gasto->estado;

            // Si se sube nueva evidencia, borrar la antigua y guardar la nueva.
            if ($request->hasFile('evidencia')) {
                Storage::disk('public')->delete($gasto->ruta_evidencia);
                $validatedData['ruta_evidencia'] = $request->file('evidencia')->store('evidencias_gastos', 'public');
            }

            // Actualizar datos del gasto y cambiar estado
            $gasto->update($validatedData);
            $gasto->estado = 'Pendiente de Aprobación Jefatura';
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, 'Gasto corregido y reenviado para aprobación.');
        });

        return response()->json(['message' => 'Gasto corregido y reenviado exitosamente.', 'gasto' => $gasto]);
    }

    // MÉTODOS ADICIONALES PARA COMPLETAR EL FLUJO

    /**
     * Finaliza un gasto marcándolo como 'Contabilizado'. (Acción de Administración)
     */
    public function finalizeAsAccounted(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        // Autorización y Validación de Estado
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }
        if ($gasto->estado !== 'Aprobado por Jefatura') {
            return response()->json(['message' => 'Solo se pueden contabilizar gastos aprobados.'], 409);
        }

        $estadoAnterior = $gasto->estado;
        $gasto->update(['estado' => 'Contabilizado']);
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto procesado y cerrado para contabilidad.'));

        return response()->json(['message' => 'Gasto marcado como Contabilizado.', 'gasto' => $gasto]);
    }

    //Rechaza un gasto de forma definitiva. (Acción de Administración)
    public function rejectFinal(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);
        $comentario = $request->input('comentario');

        // 1. Autorización
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        // 2. Validación de Estado (Un admin puede rechazar un gasto aprobado que considere incorrecto)
        if ($gasto->estado !== 'Aprobado por Jefatura') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que han sido aprobados por la jefatura.'], 409);
        }

        // 3. Ejecución
        DB::transaction(function () use ($gasto, $user, $comentario) {
            $estadoAnterior = $gasto->estado;

            // Revertir el dinero al fondo, ya que fue descontado al ser aprobado por el jefe.
            $gasto->fondoEfectivo->increment('monto_disponible', $gasto->monto_total);

            $gasto->estado = 'Rechazado';
            $gasto->motivo_rechazo = $comentario;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $comentario);
        });

        return response()->json(['message' => 'Gasto rechazado definitivamente.', 'gasto' => $gasto]);
    }

    /**
     * Helper para registrar en el historial de manera consistente.
     */
    private function registrarHistorial(Gasto $gasto, string $estadoAnterior, string $estadoNuevo, int $userId, ?string $comentario)
    {
        HistorialAprobacionGasto::create([
            'id_gasto' => $gasto->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario_accion' => $userId,
            'comentario' => $comentario,
            'fecha_cambio' => now(),
        ]);
    }

    /**
     * Muestra un gasto específico.
     */
    public function show(Gasto $gasto)
    {
        return response()->json($gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador', 'cuentaContable', 'historial.usuarioAccion']));
    }

    /**
     * Elimina un gasto.
     */
    public function destroy(Gasto $gasto)
    {
        $user = Auth::user();
        if (($gasto->estado === 'Pendiente de Aprobación Jefatura' && $user->id === $gasto->id_registrador) || $user->hasRole('super_admin')) {
            DB::transaction(function () use ($gasto) {
                if ($gasto->ruta_evidencia) {
                    Storage::disk('public')->delete($gasto->ruta_evidencia);
                }
                $gasto->historial()->delete();
                $gasto->delete();
            });
            return response()->json(['message' => 'Gasto eliminado exitosamente.']);
        }
        return response()->json(['message' => 'No tienes permiso para eliminar este gasto o ya no se encuentra en un estado que permita su eliminación.'], 403);
    }
}
