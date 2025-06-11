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
        $query = Gasto::with([

            'registrador.role',
            'registrador.area',
            'jefeAprobador:id,name,last_name',
            'cuentaContable',
            'fondoEfectivo:id_fondo,codigo_fondo'
        ]);
        $scope = $request->input('scope', 'trazabilidad');
        // Filtrado por rol para determinar qué gastos puede ver el usuario.
        if ($scope === 'aprobaciones') {
            // Si la vista es para APROBAR gastos
            if ($user->hasRole('jefe_area')) {
                // Muestra solo los gastos pendientes de su área, excluyendo los propios.
                $query->where('estado', 'Pendiente de Aprobación Jefatura')
                    ->whereHas('registrador', function ($q) use ($user) {
                        $q->where('area_id', $user->area_id)
                            ->where('id', '!=', $user->id);
                    });
            } else {
                // Otros roles no tienen bandeja de aprobación de gastos, devuelve vacío.
                $query->whereRaw('1 = 0');
            }
        } else {
            // Si la vista es para TRAZABILIDAD O AUDITORÍA
            if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                // Ven todo.
            } elseif ($user->hasRole('jefe_area')) {
                // Ve todos los gastos de su área (suyos y de su equipo).
                $query->whereHas('registrador', function ($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });
            } else {
                // Un colaborador solo ve sus propios gastos.
                $query->where('id_registrador', $user->id);
            }
        }

        // Filtro por estado del gasto
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // Filtro por código de gasto
        if ($request->filled('codigo_gasto')) {
            $query->where('codigo_gasto', 'like', '%' . $request->codigo_gasto . '%');
        }

        // Filtro por nombre o apellido del registrador
        if ($request->filled('registrador_name')) {
            $searchTerm = strtolower($request->registrador_name);
            $query->whereHas('registrador', function ($q) use ($searchTerm) {
                $q->where(DB::raw("CONCAT(LOWER(name), ' ', LOWER(last_name))"), 'like', '%' . $searchTerm . '%');
            });
        }

        // Filtro por rango de fechas de registro (created_at)
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

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
                'tipo_documento' => 'required_if:es_declaracion_jurada,false|string|max:100',
                'serie_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:20',
                'correlativo_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:50',
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
            // 1. Obtener el fondo seleccionado.
            $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
            // 2. Verificar si el saldo disponible es suficiente.
            if ($fondo->monto_disponible < $validatedData['monto_total']) {
                // Si no hay saldo, devolvemos un error de validación (422).
                return response()->json([
                    'message' => 'Saldo insuficiente.',
                    'errors' => [
                        'monto_total' => ['El monto del gasto (S/. ' . $validatedData['monto_total'] . ') excede el saldo disponible del fondo (S/. ' . $fondo->monto_disponible . ').']
                    ]
                ], 422);
            }
            DB::beginTransaction();
            if ($validatedData['es_declaracion_jurada']) {
                $validatedData['tipo_documento'] = 'Declaración Jurada';
                $validatedData['serie_documento'] = null;
                $validatedData['correlativo_documento'] = null;
            }
            $path = $request->file('evidencia')->store('evidencias_gastos', 'public');

            $estadoInicial = 'Pendiente de Aprobación Jefatura';
            $idJefeAprobador = null;
            $comentarioHistorial = 'Gasto registrado por el colaborador.';

            // Si el que registra es un Jefe de Área, el gasto se auto-aprueba en el primer nivel.
            if ($user->hasRole('jefe_area')) {
                $estadoInicial = 'Aprobado por Jefatura';
                $idJefeAprobador = $user->id; // El jefe es su propio aprobador inicial
                $comentarioHistorial = 'Gasto registrado y auto-aprobado por Jefe de Área.';

                // Descontar del saldo del fondo inmediatamente
                $fondo->monto_disponible -= $validatedData['monto_total'];
                $fondo->save();
            }

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
            $gasto->load(['registrador.role', 'registrador.area']);
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
        return response()->json($gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador', 'cuentaContable', 'historial.usuarioAccion']));
    }

    /**
     * Actualiza el estado de un gasto (para aprobaciones, observaciones y devoluciones).
     * Este método centraliza toda la máquina de estados del flujo.
     */
    public function actualizarEstado(Request $request, Gasto $gasto)
    {
        $validatedData = $request->validate([
            'accion' => ['required', Rule::in(['aprobar_jefe', 'observar_adm', 'devolver_jefe', 'reenviar_colaborador', 'contabilizar', 'rechazar_final'])],
            'comentario' => 'nullable|string|max:2000',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120' // Para la acción de reenviar
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            $estadoAnterior = $gasto->estado;
            $comentario = $validatedData['comentario'] ?? '';

            switch ($validatedData['accion']) {
                case 'aprobar_jefe':
                    $this->handleAprobarJefe($gasto, $user, $comentario);
                    break;
                case 'observar_adm':
                    $this->handleObservarAdm($gasto, $user, $comentario);
                    break;
                case 'devolver_jefe':
                    $this->handleDevolverJefe($gasto, $user, $comentario);
                    break;
                case 'reenviar_colaborador':
                    // Nota: Esta acción podría necesitar más lógica, como actualizar la evidencia.
                    $this->handleReenviarColaborador($gasto, $user);
                    break;
                case 'contabilizar':
                    $this->handleContabilizar($gasto, $user, $comentario);
                    break;
                case 'rechazar_final':
                    $this->handleRechazarFinal($gasto, $user, $comentario);
                    break;
                default:
                    throw new \Exception("Acción no reconocida.");
            }

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $comentario);

            DB::commit();
            $gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador']);
            return response()->json(['message' => 'Gasto actualizado exitosamente.', 'gasto' => $gasto]);
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
    private function handleRechazarFinal(Gasto $gasto, $user, $comentario)
    {
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) throw new \Exception('No tienes permiso para rechazar este gasto.');
        if ($gasto->estado !== 'Aprobado por Jefatura') throw new \Exception('Solo se pueden rechazar gastos que ya han sido aprobados por la jefatura.');
        if (empty($comentario)) throw new \Exception('El motivo del rechazo es obligatorio.');

        $gasto->estado = 'Rechazado';
        $gasto->motivo_rechazo = $comentario;
        $gasto->save();

        $fondo = $gasto->fondoEfectivo;
        $fondo->monto_disponible += $gasto->monto_total; // Devuelve el monto al fondo.
        $fondo->save();

        $this->registrarHistorial($gasto, $gasto->estado, 'rechazar_final', $user->id, 'Gasto Rechazado definitivamente.');
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
