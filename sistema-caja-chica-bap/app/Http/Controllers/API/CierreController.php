<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CierreMensual;
use App\Models\ExcepcionCierre;
use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CierreController extends Controller
{
    /**
     * Muestra el estado de todos los períodos mensuales.
     */
    protected $activityLogService;
    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }
    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'sometimes|integer|min:2020|max:2099',
            'estado' => 'sometimes|string|in:Abierto,Cerrado',
        ]);

        $year = $validated['year'] ?? Carbon::now()->year;

        $excepcionesActivas = ExcepcionCierre::where('fecha_expiracion', '>=', Carbon::today())
            ->whereHas('cierreMensual', function ($query) use ($year) {
                $query->whereYear('periodo', $year);
            })
            ->with('cierreMensual:id,periodo') 
            ->get();
        
        $excepcionesPorPeriodo = $excepcionesActivas->keyBy(function ($item) {
            return Carbon::parse($item->cierreMensual->periodo)->format('Y-m');
        });
        // Obtener solo los cierres del año solicitado.
        $query = CierreMensual::whereYear('periodo', $year);
        $cierres = $query->get();

        $cierresPorPeriodo = $cierres->keyBy(function ($item) {
            return Carbon::parse($item->periodo)->format('Y-m');
        });

        $resultado = [];
        for ($i = 1; $i <= 12; $i++) {
            $fechaPeriodo = Carbon::createFromDate($year, $i, 1);
            $periodoKey = $fechaPeriodo->format('Y-m');
            $estadoActual = 'Abierto';
            $cierreExistente = null;

            if ($cierresPorPeriodo->has($periodoKey)) {
                $cierreExistente = $cierresPorPeriodo->get($periodoKey);
                $estadoActual = $cierreExistente->estado;
            }

            // Aplicar el filtro de estado.
            if (isset($validated['estado']) && $estadoActual !== $validated['estado']) {
                continue;
            }

            $cierreParaEnviar = $cierreExistente ?? [
                'id' => null,
                'periodo' => $fechaPeriodo->format('Y-m-d'),
                'estado' => 'Abierto',
            ];
            $cierreParaEnviar['tiene_excepciones_activas'] = $excepcionesPorPeriodo->has($periodoKey);
            $resultado[] = $cierreParaEnviar;
        }
        return response()->json($resultado);
    }

    /**
     * Actualiza el estado de un período mensual (lo cierra o lo reabre).
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'periodo' => 'required|date_format:Y-m',
            'estado' => 'required|in:Abierto,Cerrado',
        ]);

        $user = Auth::user();
        // El primer día del mes a partir del formato 'Y-m'.
        $fechaPeriodo = Carbon::createFromFormat('Y-m', $validated['periodo'])->startOfMonth();
        if ($validated['estado'] === 'Abierto') {
            $existenExcepciones = ExcepcionCierre::where('fecha_expiracion', '>=', Carbon::today())
                ->whereHas('cierreMensual', function ($query) use ($fechaPeriodo) {
                    $query->where('periodo', $fechaPeriodo);
                })
                ->exists();
            
            if ($existenExcepciones) {
                return response()->json([
                    'message' => 'No se puede reabrir el período porque existen excepciones activas. Por favor, revoque todas las excepciones para este mes antes de continuar.'
                ], 409);
            }
        }
        // Se busca el registro del mes o se crea si no existe.
        $cierre = CierreMensual::firstOrNew(
            ['periodo' => $fechaPeriodo],
            ['id_usuario_accion' => $user->id]
        );
        // Capturamos el estado antes del cambio
        $estadoAnterior = $cierre->estado ?? 'Abierto';
        if ($estadoAnterior === $validated['estado']) {
            return response()->json([
                'message' => "El período {$validated['periodo']} ya se encuentra en estado '{$validated['estado']}'.",
                'cierre' => $cierre,
            ]);
        }
        $cierre->estado = $validated['estado'];
        $cierre->id_usuario_accion = $user->id;
        $cierre->save();

        // Determinamos el tipo de acción basado en el nuevo estado.
        $actionType = ($validated['estado'] === 'Cerrado') ? 'PERIODO_CERRADO' : 'PERIODO_REABIERTO';

        // Creamos una descripción detallada para guardar en el log.
        $properties = [
            'descripcion' => "El usuario cambió el estado del período {$validated['periodo']} de '{$estadoAnterior}' a '{$validated['estado']}'.",
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $validated['estado'],
        ];

        $this->activityLogService->log($actionType, $cierre, $properties, $user);

        return response()->json([
            'message' => "El período {$validated['periodo']} ha sido actualizado a '{$validated['estado']}' exitosamente.",
            'cierre' => $cierre,
        ]);
    }
}
