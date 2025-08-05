<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\FondoEfectivo;
use App\Models\Gasto;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtiene todos los datos necesarios para el dashboard, adaptados al rol del usuario.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDashboardData(Request $request)
    {
        $user = Auth::user();

        // 1. Validar los filtros de fecha opcionales
        $validated = $request->validate([
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin' => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'area_id' => 'nullable|integer|exists:areas,id',
            'responsable_id' => 'nullable|integer|exists:users,id',
        ]);

        $fechaInicio = $validated['fecha_inicio'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $fechaFin = $validated['fecha_fin'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        // 2. Definir el alcance de la consulta según el rol del usuario
        $fondoQuery = FondoEfectivo::query();
        $gastoQuery = Gasto::query();

        if ($user->hasRole('jefe_area')) {
            $areaId = $user->area_id;
            $fondoQuery->where('id_area', $areaId);
            $gastoQuery->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $areaId));
        } elseif ($user->hasRole('colaborador')) {
            $userId = $user->id;
            $fondoQuery->where('id_responsable', $userId);
            $gastoQuery->where('id_registrador', $userId);
        }
        // Estos filtros solo se aplican si el usuario es admin, y si los filtros fueron enviados desde el frontend.
        if ($user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            if (!empty($validated['area_id'])) {
                $fondoQuery->where('id_area', $validated['area_id']);
                $gastoQuery->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $validated['area_id']));
            }
            if (!empty($validated['responsable_id'])) {
                $fondoQuery->where('id_responsable', $validated['responsable_id']);
                $gastoQuery->where('id_registrador', $validated['responsable_id']);
            }
        }
        // 3. Aplicar filtros de fecha si se proporcionaron
        $gastoQuery->whereBetween('fecha_documento', [$fechaInicio, $fechaFin]);

        // 4. Clonar queries para usarlas en diferentes cálculos
        $kpiFondoQuery = clone $fondoQuery;
        $kpiGastoQuery = clone $gastoQuery;
        $gastosCategoriaQuery = clone $gastoQuery;
        $gastosEstadoQuery = clone $gastoQuery;
        $fondosTipoQuery = clone $fondoQuery;
        $usuariosGastosQuery = clone $gastoQuery;
        $rendicionesQuery = clone $gastoQuery;
        $alertasFondoQuery = clone $fondoQuery;
        $alertasGastoQuery = clone $gastoQuery;

        // 5. Calcular todas las métricas
        $kpisGenerales = $this->getKpisGenerales($kpiFondoQuery, $kpiGastoQuery);
        $kpisGastos = $this->getKpisGastos($kpiGastoQuery); // Este método ahora agrupa varias métricas de gastos

        $gastosPorCategoria = $this->getGastosPorCategoria($gastosCategoriaQuery);
        $gastosPorEstado = $this->getGastosPorEstado($gastosEstadoQuery);
        $fondosPorTipo = $this->getFondosPorTipo($fondosTipoQuery);
        $usuariosConMayorGastos = $this->getUsuariosConMayorGastos($usuariosGastosQuery);
        $cumplimientoRendiciones = $this->getCumplimientoRendiciones($rendicionesQuery);
        $alertas = $this->getAlertas($alertasGastoQuery, $alertasFondoQuery);
        $data = [
            'kpisGenerales' => $kpisGenerales,
            'kpisGastos' => $kpisGastos,
            'gastosPorCategoria' => $gastosPorCategoria,
            'gastosPorEstado' => $gastosPorEstado,
            'fondosPorTipo' => $fondosPorTipo,
            'usuariosConMayorGastos' => $usuariosConMayorGastos,
            'cumplimientoRendiciones' => $cumplimientoRendiciones,
            'alertas' => $alertas,
            'evolucionMensual' => $this->getEvolucionMensual(clone $gastoQuery, clone $fondoQuery),
        ];

        // 6. Añadir métricas exclusivas para administradores
        if ($user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            $data['topAreasPorGasto'] = $this->getTopAreasPorGasto(clone $gastoQuery);
        }

        return response()->json($data);
    }

    private function getKpisGenerales($fondoQuery, $gastoQuery)
    {
        $fondosActivos = $fondoQuery->where('estado', 'Activo')->get();
        $montoTotalAsignado = $fondosActivos->sum('monto_aprobado');

        // Suma de todos los gastos que no han sido rechazados
        $montoTotalGastado = $gastoQuery->where('estado', '!=', 'Rechazado')->sum('monto_total');
        $porcentajeEjecucion = ($montoTotalAsignado > 0)
            ? ($montoTotalGastado / $montoTotalAsignado) * 100
            : 0;

        return [
            'total_fondos_activos' => $fondosActivos->count(),
            'monto_total_asignado' => (float) $montoTotalAsignado,
            'monto_total_gastado' => (float) $montoTotalGastado,
            'porcentaje_ejecucion' => round($porcentajeEjecucion, 2),
        ];
    }
    private function getKpisGastos($gastoQuery)
    {
        $gastosEnProceso = (clone $gastoQuery)->whereIn('estado', ['Pendiente de Aprobación', 'Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Observado'])->count();
        $gastosFinalizados = (clone $gastoQuery)->whereIn('estado', ['Contabilizado', 'Repuesto', 'Rechazado'])->count();
        $gastosRechazados = (clone $gastoQuery)->where('estado', 'Rechazado')->count();
        $montoTotalDeclarado = (clone $gastoQuery)->where('estado', '!=', 'Rechazado')->sum('monto_total');
        $cantidadTotalDeclarada = (clone $gastoQuery)->where('estado', '!=', 'Rechazado')->count();
        $cantidadTotalGeneral = (clone $gastoQuery)->count();

        $porcentajeRechazados = ($cantidadTotalGeneral > 0) ? ($gastosRechazados / $cantidadTotalGeneral) * 100 : 0;

        return [
            'gastos_en_proceso' => $gastosEnProceso,
            'gastos_finalizados' => $gastosFinalizados,
            'gastos_rechazados' => $gastosRechazados,
            'monto_total_declarado' => (float) $montoTotalDeclarado,
            'cantidad_total_declarada' => $cantidadTotalDeclarada,
            'cantidad_total_general' => $cantidadTotalGeneral,
            'porcentaje_gastos_rechazados' => round($porcentajeRechazados, 2),
        ];
    }
    private function getGastosPorCategoria($gastoQuery)
    {
        $data = $gastoQuery->join('gastos_proyectados', 'gastos.id_gasto_proyectado', '=', 'gastos_proyectados.id_gasto_proyectado')
            ->select('gastos_proyectados.descripcion as categoria', DB::raw('SUM(gastos.monto_total) as total'))
            ->where('gastos.estado', '!=', 'Rechazado')
            ->groupBy('gastos_proyectados.descripcion')
            ->orderBy('total', 'desc')
            ->limit(10)
            ->get();

        return [
            'labels' => $data->pluck('categoria'),
            'data' => $data->pluck('total')->map(fn($val) => (float) $val),
        ];
    }

    private function getGastosPorEstado($gastoQuery)
    {
        return $gastoQuery->select('estado', DB::raw('COUNT(*) as total'))
            ->groupBy('estado')
            ->get()
            ->pluck('total', 'estado');
    }

    private function getFondosPorTipo($fondoQuery)
    {
        $data = $fondoQuery->where('estado', 'Activo')
            ->select('tipo_fondo', DB::raw('COUNT(*) as total'))
            ->groupBy('tipo_fondo')
            ->get();

        return [
            'labels' => $data->pluck('tipo_fondo'),
            'data' => $data->pluck('total'),
        ];
    }

    private function getUsuariosConMayorGastos($gastoQuery)
    {
        return $gastoQuery->join('users', 'gastos.id_registrador', '=', 'users.id')
            ->select(

                DB::raw("CONCAT(users.name, ' ', users.last_name) as usuario"),
                DB::raw('COUNT(gastos.id) as cantidad_gastos'),
                DB::raw('SUM(gastos.monto_total) as monto_total')
            )
            ->groupBy('users.id', 'users.name', 'users.last_name')
            ->orderBy('cantidad_gastos', 'desc')
            ->limit(5) // Top 5 es más estándar para dashboards.
            ->get()
            ->map(function ($item) {
                return [
                    'usuario' => $item->usuario,
                    'cantidad_gastos' => $item->cantidad_gastos,
                    'monto_total' => (float) $item->monto_total
                ];
            });
    }

    private function getCumplimientoRendiciones($gastoQuery)
    {
        // Clonar la query para no afectar otros cálculos
        $query = clone $gastoQuery;
        $gastosDelPeriodo = $query->get();

        if ($gastosDelPeriodo->isEmpty()) {
            return [
                'porcentaje_cumplimiento' => 0,
                'rendiciones_a_tiempo' => 0,
                'rendiciones_fuera_plazo' => 0,
                'pendientes_rendicion' => 0,
                'total_gastos' => 0
            ];
        }

        $totalGastos = $gastosDelPeriodo->count();
        $rendicionesATiempo = 0;
        $rendicionesFueraPlazo = 0;
        // Se definen los estados que se consideran "pendientes".
        $estadosPendientes = [
            'Pendiente de Aprobación',
            'Pendiente de Validación DJ',
            'Pendiente de Validación Contable',
            'Observado'
        ];

        $pendientesRendicion = $gastosDelPeriodo->whereIn('estado', $estadosPendientes)->count();
        // Se consideran "rendidos" los gastos que ya han sido contabilizados o repuestos.
        $gastosRendidos = $gastosDelPeriodo->whereIn('estado', ['Contabilizado', 'Repuesto']);
        foreach ($gastosRendidos as $gasto) {
            // La fecha límite es el último día del mes de la fecha del documento.
            $fechaLimite = Carbon::parse($gasto->fecha_documento)->endOfMonth();
            // Se asume que 'fecha_rendicion' se guarda cuando se contabiliza. Usamos 'updated_at' como fallback.
            $fechaRendicion = Carbon::parse($gasto->fecha_rendicion ?? $gasto->updated_at);
            if ($fechaRendicion->lte($fechaLimite)) {
                $rendicionesATiempo++;
            } else {
                $rendicionesFueraPlazo++;
            }
        }
        // El porcentaje de cumplimiento se calcula sobre los gastos que ya deberían haber sido rendidos.
        $totalRendidos = $rendicionesATiempo + $rendicionesFueraPlazo;
        $porcentajeCumplimiento = ($totalRendidos > 0) ? ($rendicionesATiempo / $totalRendidos) * 100 : 0;

        return [
            'porcentaje_cumplimiento' => round($porcentajeCumplimiento, 2),
            'rendiciones_a_tiempo' => $rendicionesATiempo,
            'rendiciones_fuera_plazo' => $rendicionesFueraPlazo,
            'pendientes_rendicion' => $pendientesRendicion,
            'total_gastos' => $totalGastos
        ];
    }

    private function getAlertas($gastoQuery, $fondoQuery)
    {
        $alertas = [];
        $user = Auth::user();
        $estadosAccionables = [
            'Pendiente de Validación DJ',
            'Pendiente de Validación Contable'
        ];
        // 1. Alertas de sobregiro
        $fondosConSobregiro = $fondoQuery->where('estado', 'Activo')
            ->withSum(['gastos' => fn($q) => $q->where('estado', '!=', 'Rechazado')], 'monto_total')
            ->get()
            ->filter(fn($fondo) => $fondo->gastos_sum_monto_total > $fondo->monto_aprobado);

        if ($fondosConSobregiro->count() > 0) {
            $alertas[] = [
                'tipo' => 'sobregiro',
                'mensaje' => 'Hay ' . $fondosConSobregiro->count() . ' fondos con sobregiro',
                'cantidad' => $fondosConSobregiro->count(),
                'fondos' => $fondosConSobregiro->map(function ($fondo) {
                    return [
                        'id' => $fondo->id_fondo,
                        'codigo_fondo' => $fondo->codigo_fondo,
                        'monto_aprobado' => (float) $fondo->monto_aprobado,
                        'monto_gastado' => (float) $fondo->gastos_sum_monto_total,
                        'exceso' => (float) ($fondo->gastos_sum_monto_total - $fondo->monto_aprobado),
                        'es_accionable' => $fondo->estado === 'Activo',
                    ];
                })->values()
            ];
        }
        // 2. Alertas por montos inusuales

        // a) Primero, calculamos un promedio HISTÓRICO y ESTABLE.
        $queryPromedio = Gasto::query();
        // Aplicamos el mismo scope de rol para que el promedio sea relevante para el usuario.
        if ($user->hasRole('jefe_area')) {
            $queryPromedio->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $user->area_id));
        } elseif ($user->hasRole('colaborador')) {
            $queryPromedio->where('id_registrador', $user->id);
        }
        $promedioGastosHistorico = $queryPromedio->where('estado', '!=', 'Rechazado')
            ->where('fecha_documento', '>=', Carbon::now()->subDays(90))
            ->avg('monto_total') ?? 0;
        $limiteSuperior = $promedioGastosHistorico > 0 ? $promedioGastosHistorico * 3 : 0;
        // b) Ahora, buscamos gastos DENTRO DEL PERÍODO FILTRADO por el usuario
        $gastosInusuales = (clone $gastoQuery)
            ->where('monto_total', '>', $limiteSuperior)
            ->with(['registrador:id,name,last_name', 'fondoEfectivo.area:id,name'])
            ->get();

        if ($gastosInusuales->count() > 0) {
            $alertas[] = [
                'tipo' => 'monto_inusual',
                'mensaje' => 'Hay ' . $gastosInusuales->count() . ' gastos con montos inusuales',
                'cantidad' => $gastosInusuales->count(),
                'promedio_normal' => round($promedioGastosHistorico, 2),
                'limite_alerta' => round($limiteSuperior, 2),
                'gastos' => $gastosInusuales->map(function ($gasto) use ($estadosAccionables) {
                    return [
                        'id' => $gasto->id,
                        'codigo_gasto' => $gasto->codigo_gasto,
                        'monto' => (float) $gasto->monto_total,
                        'usuario' => optional($gasto->registrador)->name . ' ' . optional($gasto->registrador)->last_name,
                        'area' => optional($gasto->fondoEfectivo->area)->name ?? 'N/A',
                        'fecha' => $gasto->fecha_documento,
                        'estado' => $gasto->estado,
                        'es_accionable' => in_array($gasto->estado, $estadosAccionables),
                    ];
                })->values()
            ];
        }

        // 3. Alertas de rendiciones fuera de plazo
        $rendicionesFueraPlazo = $gastoQuery->whereNotNull('fecha_rendicion')
            ->whereColumn('fecha_rendicion', '>', 'fecha_limite_rendicion')
            ->with(['registrador:id,name,last_name', 'fondoEfectivo.area:id,name'])
            ->get();

        if ($rendicionesFueraPlazo->count() > 0) {
            $alertas[] = [
                'tipo' => 'rendicion_fuera_plazo',
                'mensaje' => 'Hay ' . $rendicionesFueraPlazo->count() . ' rendiciones fuera de plazo',
                'cantidad' => $rendicionesFueraPlazo->count(),
                'gastos' => $rendicionesFueraPlazo->map(function ($gasto) use ($estadosAccionables) {
                    $diasRetraso = Carbon::parse($gasto->fecha_rendicion)
                        ->diffInDays(Carbon::parse($gasto->fecha_limite_rendicion));
                    return [
                        'id' => $gasto->id_gasto,
                        'codigo_gasto' => $gasto->codigo_gasto,
                        'usuario' => $gasto->registrador->name . ' ' . $gasto->registrador->last_name ?? 'N/A',
                        'area' => $gasto->fondoEfectivo->area->name ?? 'N/A',
                        'dias_retraso' => $diasRetraso,
                        'fecha_limite' => $gasto->fecha_limite_rendicion,
                        'fecha_rendicion' => $gasto->fecha_rendicion,
                        'estado' => $gasto->estado,
                        'es_accionable' => in_array($gasto->estado, $estadosAccionables),
                    ];
                })->values()
            ];
        }

        return $alertas;
    }
    private function getEvolucionMensual($gastoQuery, $fondoQuery)
    {
        $endDate = Carbon::now()->endOfMonth();
        $startDate = Carbon::now()->subMonths(23)->startOfMonth();

        // 1. Obtener gastos agrupados por mes
        $gastosMensuales = $gastoQuery
            ->whereBetween('fecha_documento', [$startDate, $endDate])
            ->where('estado', '!=', 'Rechazado')
            ->select(
                DB::raw("DATE_FORMAT(fecha_documento, '%Y-%m') as mes"),
                DB::raw('SUM(monto_total) as monto_gastado')
            )
            ->groupBy('mes')
            ->orderBy('mes', 'asc')
            ->get()
            ->keyBy('mes');

        // 2. Obtener presupuesto (monto aprobado de fondos activos) por mes
        $presupuestoMensual = $fondoQuery
            ->where('estado', 'Activo')
            ->select(
                DB::raw("DATE_FORMAT(fecha_apertura, '%Y-%m') as mes"),
                DB::raw('SUM(monto_aprobado) as monto_asignado')
            )
            ->where('fecha_apertura', '<=', $endDate)
            ->groupBy('mes')
            ->orderBy('mes', 'asc')
            ->get()
            ->keyBy('mes');

        // 3. Unir los datos en una sola estructura
        $resultado = [];
        $currentDate = $startDate->clone();

        while ($currentDate <= $endDate) {
            $mesKey = $currentDate->format('Y-m');

            $resultado[] = [
                'mes' => $mesKey,
                'gastos' => (float) ($gastosMensuales[$mesKey]->monto_gastado ?? 0),
                'presupuesto' => (float) ($presupuestoMensual[$mesKey]->monto_asignado ?? 0),
            ];

            $currentDate->addMonth();
        }

        return $resultado;
    }

    private function getTopAreasPorGasto($gastoQuery)
    {
        return $gastoQuery->join('fondo_efectivo', 'gastos.id_fondo_efectivo', '=', 'fondo_efectivo.id_fondo')
            ->join('areas', 'fondo_efectivo.id_area', '=', 'areas.id')
            ->select('areas.name as area', DB::raw('SUM(gastos.monto_total) as total'))
            ->where('gastos.estado', '!=', 'Rechazado')
            ->groupBy('areas.name')
            ->orderBy('total', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'area' => $item->area,
                    'total' => (float) $item->total
                ];
            });
    }
}
