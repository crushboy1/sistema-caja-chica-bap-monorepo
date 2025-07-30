<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\CierreMensual;
use Carbon\Carbon;

class CheckPeriodoCerrado
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        $gastosAValidar = [];
        $errors = [];
        if ($request->has('gastos') && is_array($request->input('gastos'))) {
            // Caso 1: Creación de múltiples gastos
            $gastosAValidar = $request->input('gastos');
        } else if ($request->has('fecha_documento')) {
            // Caso 2: Actualización de un único gasto
            $gastosAValidar[] = $request->all();
        }

        if (empty($gastosAValidar)) {
            return $next($request);
        }
        foreach ($gastosAValidar as $index => $gastoData) {
            if (!isset($gastoData['fecha_documento'])) {
                continue;
            }

            $fechaGasto = Carbon::parse($gastoData['fecha_documento']);
            $periodo = $fechaGasto->startOfMonth()->toDateString();

            // 1. Verificar si el período está cerrado.
            $cierre = CierreMensual::where('periodo', $periodo)->where('estado', 'Cerrado')->first();

            if ($cierre) {
                // 2. Si está cerrado, verificar si el usuario tiene una excepción activa.
                $excepcionActiva = $cierre->excepciones()
                    ->where('id_usuario_excepcion', $user->id)
                    ->where('fecha_expiracion', '>=', Carbon::today())
                    ->exists();
                // 3. Si el período está cerrado Y NO hay una excepción activa, rechazar la solicitud.
                if (!$excepcionActiva) {
                    // En lugar de retornar, se añade el error a la lista.
                    $errors[] = "Gasto #" . ($index + 1) . ": El período para la fecha {$fechaGasto->format('d/m/Y')} ya ha sido cerrado.";
                }
            }
        }
        if (!empty($errors)) {
            $errorMessage = "No se pueden registrar los gastos debido a los siguientes errores de período:<br><ul class='text-left mt-2 list-disc list-inside'>" . implode('', array_map(fn($err) => "<li>$err</li>", $errors)) . "</ul>";

            return response()->json([
                'message' => $errorMessage
            ], 403);
        }
        return $next($request);
    }
}
