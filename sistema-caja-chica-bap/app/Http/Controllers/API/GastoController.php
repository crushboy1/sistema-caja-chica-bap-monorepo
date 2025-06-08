<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\FondoEfectivo;
use App\Models\HistorialAprobacionGasto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class GastoController extends Controller
{
    /**
     * Muestra una lista de gastos, filtrada por el rol del usuario y los parámetros de la request.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Gasto::with(['registrador:id,name,last_name', 'jefeAprobador:id,name,last_name', 'cuentaContable', 'fondoEfectivo:id_fondo,codigo_fondo']);

        // Filtrado por rol para determinar qué gastos puede ver el usuario.
        if ($user->hasRole('colaborador')) {
            $query->where('id_registrador', $user->id);
        } elseif ($user->hasRole('jefe_area')) {
            // Un jefe de área ve los gastos registrados por cualquier miembro de su área.
            $query->whereHas('registrador', function ($q) use ($user) {
                // Asumiendo que el modelo User tiene una relación 'area' o un campo area_id.
                // Es crucial que los usuarios tengan su 'area_id' asignado en la BD.
                $q->where('area_id', $user->area_id);
            });
        } // Los roles de admin/gerente ven todo por defecto, no se aplica filtro de query aquí.

        // Añadir filtros de búsqueda del frontend
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        // ... (añadir aquí más filtros si son necesarios, como por fecha) ...

        $gastos = $query->orderBy('created_at', 'desc')->get();

        return response()->json($gastos);
    }

    /**
     * Almacena un nuevo gasto en la base de datos (Paso 1 del flujo).
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'id_fondo_efectivo' => 'required|exists:fondo_efectivo,id_fondo',
                'fecha_documento' => 'required|date',
                'tipo_documento' => 'required|string|max:100',
                'serie_documento' => 'nullable|string|max:20',
                'correlativo_documento' => 'nullable|string|max:50',
                'monto_total' => 'required|numeric|min:0.01',
                'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
                'glosa' => 'required|string|max:1000',
                'evidencia' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
                'es_declaracion_jurada' => 'required|boolean',
                'moneda' => 'required|string|max:3',
                'pertenece_proyecto' => 'required|boolean',
                'comentario' => 'nullable|string|max:2000',
            ]);

            $user = Auth::user();

            DB::beginTransaction();

            $path = $request->file('evidencia')->store('evidencias_gastos', 'public');

            $gasto = Gasto::create([
                'id_fondo_efectivo' => $validatedData['id_fondo_efectivo'],
                'id_registrador' => $user->id,
                'fecha_documento' => $validatedData['fecha_documento'],
                'tipo_documento' => $validatedData['tipo_documento'],
                'serie_documento' => $validatedData['serie_documento'],
                'correlativo_documento' => $validatedData['correlativo_documento'],
                'monto_total' => $validatedData['monto_total'],
                'id_cuenta_contable' => $validatedData['id_cuenta_contable'],
                'moneda' => $validatedData['moneda'],
                'glosa' => $validatedData['glosa'],
                'ruta_evidencia' => $path,
                'es_declaracion_jurada' => $validatedData['es_declaracion_jurada'],
                'pertenece_proyecto' => $validatedData['pertenece_proyecto'],
                'comentario' => $validatedData['comentario'],
                'estado' => 'Pendiente de Aprobación Jefatura',
            ]);

            $this->registrarHistorial($gasto, 'N/A', $gasto->estado, $user->id, 'Gasto registrado por el colaborador.');

            DB::commit();

            return response()->json(['message' => 'Gasto registrado exitosamente.', 'gasto' => $gasto], 201);
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Error de validación.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al registrar gasto: " . $e->getMessage());
            return response()->json(['message' => 'Ocurrió un error al registrar el gasto.'], 500);
        }
    }

    /**
     * Muestra un gasto específico con todas sus relaciones.
     */
    public function show(Gasto $gasto)
    {
        // Se puede añadir una Policy para una autorización más granular si es necesario.
        return response()->json($gasto->load(['registrador', 'jefeAprobador', 'cuentaContable', 'historial.usuarioAccion']));
    }

    /**
     * Actualiza el estado de un gasto (para aprobaciones, observaciones y devoluciones).
     * Este método centraliza toda la máquina de estados del flujo.
     */
    public function update(Request $request, Gasto $gasto)
    {
        $validatedData = $request->validate([
            'accion' => ['required', Rule::in(['aprobar_jefe', 'rechazar_jefe', 'observar_adm', 'devolver_jefe', 'reenviar_colaborador', 'contabilizar'])],
            'comentario' => 'nullable|string|max:1000',
        ]);

        $user = Auth::user();
        $accion = $validatedData['accion'];
        $comentario = $validatedData['comentario'] ?? '';
        $estadoAnterior = $gasto->estado;

        DB::beginTransaction();
        try {
            switch ($accion) {
                // PASO 2: Aprobación por Jefe de Área
                case 'aprobar_jefe':
                    $this->handleAprobarJefe($gasto, $user, $comentario);
                    break;

                // PASO 3: Observación por Administración
                case 'observar_adm':
                    $this->handleObservarAdm($gasto, $user, $comentario);
                    break;

                // PASO 4 (Parte 1): Devolución por Jefe de Área
                case 'devolver_jefe':
                    $this->handleDevolverJefe($gasto, $user, $comentario);
                    break;

                // PASO 4 (Parte 2): Reenvío por Colaborador (aquí se usa el método 'store' o uno específico de edición)
                // Este caso se manejaría mejor en un método de edición separado, pero se puede simular aquí.
                // Por ahora, asumimos que la edición de datos (ej. nuevo archivo) se maneja en otro endpoint.
                // Aquí solo gestionamos el cambio de estado.
                case 'reenviar_colaborador':
                    $this->handleReenviarColaborador($gasto, $user);
                    break;

                // PASO 5: Contabilización
                case 'contabilizar':
                    $this->handleContabilizar($gasto, $user);
                    break;

                default:
                    throw new \Exception("Acción no reconocida.");
            }

            DB::commit();
            return response()->json(['message' => 'Gasto actualizado exitosamente.', 'gasto' => $gasto->fresh()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al actualizar gasto {$gasto->id}: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    // --- MÉTODOS PRIVADOS PARA MANEJAR LA LÓGICA DE ESTADOS ---

    private function handleAprobarJefe(Gasto $gasto, $user, $comentario)
    {
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            throw new \Exception('No tienes permiso para aprobar este gasto.');
        }
        if (!in_array($gasto->estado, ['Pendiente de Aprobación Jefatura', 'Devuelto para Corrección'])) {
            throw new \Exception('El gasto no está en un estado válido para ser aprobado por la jefatura.');
        }

        $fondo = $gasto->fondoEfectivo;
        if ($fondo->monto_disponible < $gasto->monto_total) {
            throw new \Exception('El fondo no tiene saldo suficiente para cubrir este gasto.');
        }

        $gasto->estado = 'Aprobado por Jefatura';
        $gasto->id_jefe_aprobador = $user->id;
        $gasto->save();

        $fondo->monto_disponible -= $gasto->monto_total;
        $fondo->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'Aprobado por Jefatura', $user->id, $comentario ?: 'Gasto aprobado por Jefe de Área.');
    }

    private function handleObservarAdm(Gasto $gasto, $user, $comentario)
    {
        if (!$user->hasRole(['jefe_administracion', 'super_admin'])) {
            throw new \Exception('No tienes permiso para observar este gasto.');
        }
        if ($gasto->estado !== 'Aprobado por Jefatura') {
            throw new \Exception('Solo se pueden observar gastos previamente aprobados por la jefatura.');
        }

        $gasto->estado = 'Observado por Administración';
        $gasto->motivo_observacion_adm = $comentario;
        $gasto->save();

        // Revertir el saldo
        $fondo = $gasto->fondoEfectivo;
        $fondo->monto_disponible += $gasto->monto_total;
        $fondo->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'Observado por Administración', $user->id, $comentario);
    }

    private function handleDevolverJefe(Gasto $gasto, $user, $comentario)
    {
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            throw new \Exception('No tienes permiso para devolver este gasto.');
        }
        if ($gasto->estado !== 'Observado por Administración') {
            throw new \Exception('Solo se pueden devolver gastos observados por administración.');
        }

        $gasto->estado = 'Devuelto para Corrección';
        $gasto->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'Devuelto para Corrección', $user->id, $comentario ?: 'Devuelto al colaborador para corrección.');
    }

    private function handleReenviarColaborador(Gasto $gasto, $user)
    {
        if ($user->id !== $gasto->id_registrador) {
            throw new \Exception('Solo el registrador original puede reenviar el gasto.');
        }
        if ($gasto->estado !== 'Devuelto para Corrección') {
            throw new \Exception('El gasto no está en estado de corrección.');
        }

        $gasto->estado = 'Pendiente de Aprobación Jefatura';
        $gasto->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'Pendiente de Aprobación Jefatura', $user->id, 'Gasto corregido y reenviado para aprobación.');
    }

    private function handleContabilizar(Gasto $gasto, $user)
    {
        if (!$user->hasRole(['jefe_administracion', 'super_admin'])) {
            throw new \Exception('No tienes permiso para contabilizar gastos.');
        }
        if ($gasto->estado !== 'Aprobado por Jefatura') {
            throw new \Exception('Solo se pueden contabilizar gastos aprobados por la jefatura.');
        }

        $gasto->estado = 'Contabilizado';
        $gasto->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'Contabilizado', $user->id, 'Gasto procesado y cerrado para contabilidad.');
    }

    /**
     * Helper para registrar en el historial de manera consistente.
     */
    private function registrarHistorial(Gasto $gasto, string $estadoAnterior, string $estadoNuevo, int $userId, string $comentario)
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
     * Elimina un gasto.
     */
    public function destroy(Gasto $gasto)
    {
        $user = Auth::user();
        if (($gasto->estado === 'Pendiente de Aprobación Jefatura' && $user->id === $gasto->id_registrador) || $user->hasRole('super_admin')) {
            DB::transaction(function () use ($gasto) {
                Storage::disk('public')->delete($gasto->ruta_evidencia);
                $gasto->historial()->delete(); // Borrar historial antes que el gasto
                $gasto->delete();
            });
            return response()->json(['message' => 'Gasto eliminado exitosamente.']);
        }
        return response()->json(['message' => 'No tienes permiso para eliminar este gasto.'], 403);
    }
}
