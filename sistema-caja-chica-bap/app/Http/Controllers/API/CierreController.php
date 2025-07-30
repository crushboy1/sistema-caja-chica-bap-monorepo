<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CierreMensual;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CierreController extends Controller
{
    /**
     * Muestra el estado de todos los períodos mensuales.
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'sometimes|integer|min:2020|max:2099',
            'estado' => 'sometimes|string|in:Abierto,Cerrado',
        ]);

        $year = $validated['year'] ?? Carbon::now()->year;

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

            $resultado[] = $cierreExistente ?? [
                'id' => null,
                'periodo' => $fechaPeriodo->format('Y-m-d'),
                'estado' => 'Abierto',
            ];
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

        // Se busca el registro del mes o se crea si no existe.
        $cierre = CierreMensual::firstOrNew(
            ['periodo' => $fechaPeriodo],
            ['id_usuario_accion' => $user->id]
        );

        $cierre->estado = $validated['estado'];
        $cierre->id_usuario_accion = $user->id;
        $cierre->save();

        Log::info("El usuario {$user->name} ha cambiado el estado del período {$validated['periodo']} a {$validated['estado']}.");

        return response()->json([
            'message' => "El período {$validated['periodo']} ha sido actualizado a '{$validated['estado']}' exitosamente.",
            'cierre' => $cierre,
        ]);
    }
}
