<?php

namespace App\Services;

use App\Models\FondoEfectivo;
use App\Models\Gasto;
use App\Models\User;
use App\Models\SolicitudFondo;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // Aquí moveremos toda la lógica de los métodos privados del controlador.
    // Por ahora lo dejamos como un esqueleto.
    public function generateDashboardData(array $validatedData, User $user): array
    {
        // 1. Definir el rango de fechas. Si no se proveen, se usa el mes actual.
        $fechaInicio = $validatedData['fecha_inicio'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
        $fechaFin = $validatedData['fecha_fin'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');

        // =================================================================================
        // ESTRATEGIA "FETCH ONCE": OBTENER TODA LA DATA NECESARIA EN POCAS CONSULTAS
        // =================================================================================

        // 2. Construir la query base para Gastos, aplicando filtros de rol y de la UI.
        $gastosQuery = $this->buildGastosQuery($validatedData, $user, $fechaInicio, $fechaFin);

        // 3. Construir la query base para Fondos.
        $fondoQuery = $this->buildFondosQuery($validatedData, $user);

        // 4. Ejecutar las consultas y obtener las colecciones. ¡Aquí ocurren los únicos viajes a la BD!
        $gastosDelPeriodo = $gastosQuery->with([
            'registrador:id,name,last_name',
            'fondoEfectivo.area:id,name',
            'gastoProyectado:id_gasto_proyectado,descripcion'
        ])->get();

        $fondosActivos = $fondoQuery->where('estado', 'Activo')->with('historialMovimientos.usuarioAccion.role')->get();

        // =================================================================================
        // CÁLCULOS SOBRE COLECCIONES: A partir de aquí, todo es en memoria (muy rápido).
        // =================================================================================

        $data = [
            'kpisGenerales' => $this->calculateKpisGenerales($fondosActivos, $gastosDelPeriodo),
            'kpisGastos' => $this->calculateKpisGastos($gastosDelPeriodo),
            'gastosPorCategoria' => $this->calculateGastosPorCategoria($gastosDelPeriodo),
            'gastosPorEstado' => $this->calculateGastosPorEstado($gastosDelPeriodo),
            'fondosPorTipo' => $this->calculateFondosPorTipo($fondosActivos),
            'usuariosConMayorGastos' => $this->calculateUsuariosConMayorGastos($gastosDelPeriodo),
            'cumplimientoRendiciones' => $this->calculateCumplimientoRendiciones($gastosDelPeriodo),
            'alertas' => $this->calculateAlertas($gastosDelPeriodo, $fondosActivos, $user),
            'evolucionMensual' => $this->calculateEvolucionMensual($user, $validatedData),
            'evolucionGastosPorCategoria' => $this->calculateEvolucionGastosPorCategoria($gastosDelPeriodo),
            'cumplimientoPorArea' => $this->calculateCumplimientoPorArea($gastosDelPeriodo),
            'timelines' => $this->getTimelinesForDashboard($fondosActivos),
        ];

        // 6. Añadir métricas exclusivas para administradores
        if ($user->hasAnyRole(['super_admin', 'jefe_administracion', 'gerente_general'])) {
            $data['topAreasPorGasto'] = $this->calculateTopAreasPorGasto($gastosDelPeriodo);
        }

        return $data;
    }

    // =================================================================================
    // MÉTODOS PRIVADOS PARA CONSTRUIR QUERIES
    // =================================================================================

    private function buildGastosQuery(array $filters, User $user, string $fechaInicio, string $fechaFin)
    {
        $query = Gasto::query();

        if ($user->isJefeArea()) {
            $query->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $user->area_id));
        } elseif ($user->isColaborador()) {
            // Un colaborador ve los gastos que ha registrado.
            $query->where('id_registrador', $user->id);
        }

        if ($user->hasAnyRole(['super_admin', 'jefe_administracion', 'gerente_general'])) {
            if (!empty($filters['area_id'])) {
                $query->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $filters['area_id']));
            }
            if (!empty($filters['responsable_id'])) {
                $query->where('id_registrador', $filters['responsable_id']);
            }
        }

        $query->whereBetween('fecha_documento', [$fechaInicio, $fechaFin]);

        return $query;
    }

    private function buildFondosQuery(array $filters, User $user)
    {
        $query = FondoEfectivo::query();

        if ($user->isJefeArea() || $user->isColaborador()) {
            // Jefe y Colaborador ven los fondos de su área.
            $query->where('id_area', $user->area_id);
        }

        if ($user->hasAnyRole(['super_admin', 'jefe_administracion', 'gerente_general'])) {
            if (!empty($filters['area_id'])) {
                $query->where('id_area', $filters['area_id']);
            }
            if (!empty($filters['responsable_id'])) {
                $query->where('id_responsable', $filters['responsable_id']);
            }
        }

        return $query;
    }

    // =================================================================================
    // MÉTODOS PRIVADOS PARA CÁLCULOS (Ahora reciben colecciones)
    // =================================================================================
    private function calculateKpisGenerales(Collection $fondosActivos, Collection $gastosDelPeriodo): array
    {
        $montoTotalAsignado = $fondosActivos->sum('monto_aprobado');
        $montoTotalEjecutado = $gastosDelPeriodo->whereIn('estado', ['Contabilizado', 'Repuesto'])->sum('monto_total');
        $porcentajeEjecucion = ($montoTotalAsignado > 0) ? ($montoTotalEjecutado / $montoTotalAsignado) * 100 : 0;

        return [
            'total_fondos_activos' => $fondosActivos->count(),
            'monto_total_asignado' => (float) $montoTotalAsignado,
            'monto_total_ejecutado' => (float) $montoTotalEjecutado,
            'porcentaje_ejecucion' => round($porcentajeEjecucion, 2),
        ];
    }

    private function calculateKpisGastos(Collection $gastos): array
    {
        $estadosEnProceso = ['Pendiente de Aprobación', 'Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Observado'];
        $gastosEnProceso = $gastos->whereIn('estado', $estadosEnProceso);

        $gastosRechazadosCount = $gastos->where('estado', 'Rechazado')->count();
        $totalGeneral = $gastos->count();
        $porcentajeRechazados = ($totalGeneral > 0) ? ($gastosRechazadosCount / $totalGeneral) * 100 : 0;

        return [
            'gastos_en_proceso' => $gastosEnProceso->count(),
            'monto_total_en_proceso' => (float) $gastosEnProceso->sum('monto_total'),
            'gastos_finalizados' => $gastos->whereIn('estado', ['Contabilizado', 'Repuesto', 'Rechazado'])->count(),
            'gastos_rechazados' => $gastosRechazadosCount,
            'monto_total_ejecutado' => (float) $gastos->whereIn('estado', ['Contabilizado', 'Repuesto'])->sum('monto_total'),
            'porcentaje_gastos_rechazados' => round($porcentajeRechazados, 2),
            'monto_total_excedido' => (float) $gastos->whereIn('estado', ['Contabilizado', 'Repuesto'])->sum('monto_excedido_al_registrar'),
            'cantidad_total_declarada' => $gastos->where('estado', '!=', 'Rechazado')->count(),
            'cantidad_total_general' => $totalGeneral,
        ];
    }

    private function calculateAlertas(Collection $gastosDelPeriodo, Collection $fondosActivos, User $user): array
    {
        $alertas = [];
        $estadosAccionables = ['Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Pendiente de Aprobación', 'Observado'];

        // ALERTA 1 (CRÍTICA): Fondos con sobregiro real.
        $fondosConSobregiro = $fondosActivos->where('monto_disponible', '<', 0);
        if ($fondosConSobregiro->isNotEmpty()) {
            $alertas[] = [
                'tipo' => 'sobregiro_fondo',
                'severidad' => 'critica',
                'mensaje' => 'Hay ' . $fondosConSobregiro->count() . ' fondos con sobregiro que requieren liquidación.',
                'cantidad' => $fondosConSobregiro->count(),
                'detalles' => $fondosConSobregiro->map(function ($fondo) {
                    return [
                        'codigo_fondo' => $fondo->codigo_fondo,
                        'responsable_nombre' => optional($fondo->responsable)->name . ' ' . optional($fondo->responsable)->last_name,
                        'area_nombre' => optional($fondo->area)->name,
                        'exceso' => (float) abs($fondo->monto_disponible),
                        'es_accionable' => $fondo->estado === 'Activo',
                    ];
                })->values()
            ];
        }

        // ALERTA 2 (ADVERTENCIA): Gastos que excedieron su proyección inicial.
        $gastosConDesviacion = $gastosDelPeriodo->where('monto_excedido_al_registrar', '>', 0);
        if ($gastosConDesviacion->isNotEmpty()) {
            $alertas[] = [
                'tipo' => 'desviacion_proyeccion',
                'severidad' => 'advertencia',
                'mensaje' => 'Se han detectado ' . $gastosConDesviacion->count() . ' gastos que excedieron su proyección.',
                'cantidad' => $gastosConDesviacion->count(),
                'detalles' => $gastosConDesviacion->map(function ($gasto) {
                    return [
                        'codigo_gasto' => $gasto->codigo_gasto,
                        'categoria_nombre' => optional($gasto->gastoProyectado)->descripcion,
                        'monto_proyectado' => (float) $gasto->monto_proyectado_original,
                        'exceso' => (float) $gasto->monto_excedido_al_registrar,
                        'es_accionable' => false,
                    ];
                })->values()
            ];
        }

        // ALERTA 3 (ADVERTENCIA): Gastos con montos inusualmente altos.
        // Esta es una de las pocas excepciones donde una query separada es aceptable,
        // ya que calcula un promedio histórico que es independiente de los filtros de fecha del dashboard.
        $queryPromedio = Gasto::query();
        if ($user->isJefeArea()) {
            $queryPromedio->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $user->area_id));
        } elseif ($user->isColaborador()) {
            $queryPromedio->where('id_registrador', $user->id);
        }
        $promedioGastosHistorico = $queryPromedio->where('estado', '!=', 'Rechazado')
            ->where('fecha_documento', '>=', Carbon::now()->subDays(90))
            ->avg('monto_total') ?? 0;

        $limiteSuperior = $promedioGastosHistorico > 0 ? $promedioGastosHistorico * 3 : 0;

        if ($limiteSuperior > 0) {
            $gastosInusuales = $gastosDelPeriodo->where('monto_total', '>', $limiteSuperior);
            if ($gastosInusuales->isNotEmpty()) {
                $alertas[] = [
                    'tipo' => 'monto_inusual',
                    'severidad' => 'advertencia',
                    'mensaje' => 'Hay ' . $gastosInusuales->count() . ' gastos con montos inusuales.',
                    'cantidad' => $gastosInusuales->count(),
                    'detalles' => $gastosInusuales->map(function ($gasto) use ($estadosAccionables) {
                        return [
                            'codigo_gasto' => $gasto->codigo_gasto,
                            'monto' => (float) $gasto->monto_total,
                            'usuario' => optional($gasto->registrador)->name . ' ' . optional($gasto->registrador)->last_name,
                            'estado' => $gasto->estado,
                            'es_accionable' => in_array($gasto->estado, $estadosAccionables),
                        ];
                    })->values()
                ];
            }
        }

        // ALERTA 4 (INFORMATIVA): Rendiciones presentadas fuera de plazo.
        $rendicionesFueraPlazo = $gastosDelPeriodo->filter(function ($gasto) {
            if (is_null($gasto->fecha_rendicion) || is_null($gasto->fecha_limite_rendicion)) {
                return false;
            }
            return Carbon::parse($gasto->fecha_rendicion)->isAfter(Carbon::parse($gasto->fecha_limite_rendicion));
        });

        if ($rendicionesFueraPlazo->isNotEmpty()) {
            $alertas[] = [
                'tipo' => 'rendicion_fuera_plazo',
                'severidad' => 'informativa',
                'mensaje' => 'Hay ' . $rendicionesFueraPlazo->count() . ' rendiciones fuera de plazo.',
                'cantidad' => $rendicionesFueraPlazo->count(),
                'detalles' => $rendicionesFueraPlazo->map(function ($gasto) {
                    return [
                        'codigo_gasto' => $gasto->codigo_gasto,
                        'usuario' => optional($gasto->registrador)->name . ' ' . optional($gasto->registrador)->last_name,
                        'area' => optional($gasto->fondoEfectivo->area)->name,
                        'dias_retraso' => Carbon::parse($gasto->fecha_rendicion)->diffInDays(Carbon::parse($gasto->fecha_limite_rendicion)),
                        'es_accionable' => false,
                    ];
                })->values()
            ];
        }

        return $alertas;
    }
    private function calculateCumplimientoPorArea(Collection $gastos): array
    {
        // Solo consideramos gastos que ya han sido rendidos para el cálculo.
        $gastosRendidos = $gastos->whereIn('estado', ['Contabilizado', 'Repuesto']);

        if ($gastosRendidos->isEmpty()) {
            return [];
        }

        // Agrupamos por el nombre del área usando las relaciones ya cargadas.
        $gastosPorArea = $gastosRendidos->groupBy(function ($gasto) {
            return $gasto->fondoEfectivo->area->name ?? 'Área Desconocida';
        });

        return $gastosPorArea->map(function (Collection $gastosDeArea, $nombreArea) {
            $aTiempo = 0;

            foreach ($gastosDeArea as $gasto) {
                $fechaLimite = Carbon::parse($gasto->fecha_documento)->endOfMonth();
                // Usamos fecha_rendicion si existe, si no, updated_at como fallback.
                $fechaRendicion = Carbon::parse($gasto->fecha_rendicion ?? $gasto->updated_at);
                if ($fechaRendicion->lte($fechaLimite)) {
                    $aTiempo++;
                }
            }

            $totalRendidos = $gastosDeArea->count();
            $porcentaje = ($totalRendidos > 0) ? ($aTiempo / $totalRendidos) * 100 : 0;

            return [
                'area' => $nombreArea,
                'porcentaje_cumplimiento' => round($porcentaje, 2),
                'total_rendidos' => $totalRendidos,
            ];
        })->values()->all();
    }
    private function calculateGastosPorCategoria(Collection $gastos): array
    {
        $gastosValidos = $gastos->whereIn('estado', ['Contabilizado', 'Repuesto']);

        $data = $gastosValidos
            ->groupBy(fn($gasto) => $gasto->gastoProyectado->descripcion ?? 'Sin Categoría')
            ->map(fn(Collection $group) => $group->sum('monto_total'))
            ->sortDesc()
            ->take(10);

        return [
            'labels' => $data->keys()->all(),
            'data' => $data->values()->map(fn($val) => (float) $val)->all(),
        ];
    }
    private function calculateGastosPorEstado(Collection $gastos): Collection
    {
        return $gastos->countBy('estado');
    }
    private function calculateFondosPorTipo(Collection $fondosActivos): array
    {
        $data = $fondosActivos->countBy('tipo_fondo');

        return [
            'labels' => $data->keys(),
            'data' => $data->values(),
        ];
    }
    private function calculateUsuariosConMayorGastos(Collection $gastos): Collection
    {
        return $gastos
            ->groupBy('id_registrador')
            ->map(function (Collection $group) {
                $registrador = $group->first()->registrador;
                return [
                    'usuario' => optional($registrador)->name . ' ' . optional($registrador)->last_name,
                    'cantidad_gastos' => $group->count(),
                    'monto_total' => (float) $group->sum('monto_total'),
                ];
            })
            ->sortByDesc('monto_total')
            ->take(5)
            ->values();
    }
    private function calculateCumplimientoRendiciones(Collection $gastos): array
    {
        if ($gastos->isEmpty()) {
            return ['porcentaje_cumplimiento' => 0, 'rendiciones_a_tiempo' => 0, 'rendiciones_fuera_plazo' => 0, 'pendientes_rendicion' => 0, 'total_gastos' => 0];
        }

        $rendicionesATiempo = 0;
        $rendicionesFueraPlazo = 0;
        $estadosPendientes = ['Pendiente de Aprobación', 'Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Observado'];

        $gastosRendidos = $gastos->whereIn('estado', ['Contabilizado', 'Repuesto']);
        foreach ($gastosRendidos as $gasto) {
            $fechaLimite = Carbon::parse($gasto->fecha_documento)->endOfMonth();
            $fechaRendicion = Carbon::parse($gasto->fecha_rendicion ?? $gasto->updated_at);
            if ($fechaRendicion->lte($fechaLimite)) {
                $rendicionesATiempo++;
            } else {
                $rendicionesFueraPlazo++;
            }
        }

        $totalRendidos = $rendicionesATiempo + $rendicionesFueraPlazo;
        $porcentajeCumplimiento = ($totalRendidos > 0) ? ($rendicionesATiempo / $totalRendidos) * 100 : 0;

        return [
            'porcentaje_cumplimiento' => round($porcentajeCumplimiento, 2),
            'rendiciones_a_tiempo' => $rendicionesATiempo,
            'rendiciones_fuera_plazo' => $rendicionesFueraPlazo,
            'pendientes_rendicion' => $gastos->whereIn('estado', $estadosPendientes)->count(),
            'total_gastos' => $gastos->count()
        ];
    }
    private function calculateEvolucionMensual(User $user, array $filters): array
    {
        $endDate = Carbon::now()->endOfMonth();
        $startDate = Carbon::now()->subMonths(11)->startOfMonth();

        // 1. Obtener gastos históricos agrupados por mes, respetando el scope del usuario/área.
        $gastosHistoricosQuery = Gasto::query()->whereBetween('fecha_documento', [$startDate, $endDate]);
        $this->applyScopeToQuery($gastosHistoricosQuery, $user, $filters, 'gasto');
        $gastosMensuales = $gastosHistoricosQuery
            ->whereIn('estado', ['Contabilizado', 'Repuesto'])
            ->select(DB::raw("DATE_FORMAT(fecha_documento, '%Y-%m') as mes"), DB::raw('SUM(monto_total) as monto_gastado'))
            ->groupBy('mes')->orderBy('mes', 'asc')->get()->keyBy('mes');

        // 2. Obtener todos los fondos y sus movimientos de presupuesto (Apertura, Incremento, Decremento)
        $fondosHistoricosQuery = FondoEfectivo::query();
        $this->applyScopeToQuery($fondosHistoricosQuery, $user, $filters, 'fondo');
        $fondosConHistorial = $fondosHistoricosQuery->with(['historialMovimientos' => function ($query) {
            $query->whereIn('tipo_movimiento', ['Apertura', 'Incremento', 'Decremento'])->orderBy('fecha_movimiento', 'asc');
        }])->get();

        // 3. Reconstruir el presupuesto para cada mes del período
        $presupuestosMensuales = [];
        $currentDateIterator = $startDate->clone();
        while ($currentDateIterator <= $endDate) {
            $mesKey = $currentDateIterator->format('Y-m');
            $finDeMes = $currentDateIterator->endOfMonth();
            $presupuestoTotalDelMes = 0;

            foreach ($fondosConHistorial as $fondo) {
                $fechaApertura = Carbon::parse($fondo->fecha_apertura);
                $fechaCierre = $fondo->fecha_cierre ? Carbon::parse($fondo->fecha_cierre) : null;

                // El fondo estaba activo en este mes?
                if ($fechaApertura->lte($finDeMes) && (!$fechaCierre || $fechaCierre->gte($currentDateIterator->startOfMonth()))) {
                    // Encontrar el último movimiento de presupuesto ANTES o DURANTE este mes.
                    $ultimoMovimientoRelevante = $fondo->historialMovimientos
                        ->where('fecha_movimiento', '<=', $finDeMes)
                        ->last();

                    if ($ultimoMovimientoRelevante) {
                        $presupuestoTotalDelMes += $ultimoMovimientoRelevante->saldo_nuevo;
                    }
                }
            }
            $presupuestosMensuales[$mesKey] = $presupuestoTotalDelMes;
            $currentDateIterator->addMonth();
        }

        // 4. Unir los datos en la estructura final
        $resultado = [];
        $currentDate = $startDate->clone();
        while ($currentDate <= $endDate) {
            $mesKey = $currentDate->format('Y-m');
            $resultado[] = [
                'mes' => $mesKey,
                'gastos' => (float) ($gastosMensuales[$mesKey]->monto_gastado ?? 0),
                'presupuesto' => (float) ($presupuestosMensuales[$mesKey] ?? 0),
            ];
            $currentDate->addMonth();
        }
        return $resultado;
    }

    private function calculateEvolucionGastosPorCategoria(Collection $gastos): array
    {
        $gastosValidos = $gastos->whereIn('estado', ['Contabilizado', 'Repuesto']);
        if ($gastosValidos->isEmpty()) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = $gastosValidos->map(fn($g) => Carbon::parse($g->fecha_documento)->format('Y-m'))->unique()->sort()->values();
        $categorias = $gastosValidos->map(fn($g) => $g->gastoProyectado->descripcion ?? 'Sin Categoría')->unique()->values();

        $datasets = $categorias->map(function ($categoria) use ($labels, $gastosValidos) {
            $data = $labels->map(function ($mes) use ($categoria, $gastosValidos) {
                return (float) $gastosValidos
                    ->filter(fn($g) => (Carbon::parse($g->fecha_documento)->format('Y-m')) === $mes)
                    ->filter(fn($g) => ($g->gastoProyectado->descripcion ?? 'Sin Categoría') === $categoria)
                    ->sum('monto_total');
            });
            return ['label' => $categoria, 'data' => $data->all()];
        });

        return ['labels' => $labels->all(), 'datasets' => $datasets->all()];
    }
    private function getTimelinesForDashboard(Collection $fondosActivos): array
    {
        $fondosRecientes = $fondosActivos->sortByDesc('updated_at')->take(3);

        $timelines = [];
        foreach ($fondosRecientes as $fondo) {
            $historialSolicitudes = SolicitudFondo::where(function ($query) use ($fondo) {
                $query->where('id', $fondo->id_solicitud_apertura)->orWhere('id_solicitud_original', $fondo->id_solicitud_apertura);
            })->where('estado', 'Aprobada')->with('solicitante.role:id,display_name')->get()->map(fn($s) => [
                'tipo' => $s->tipo_solicitud,
                'fecha' => $s->updated_at,
                'monto' => $s->monto_solicitado,
                'motivo' => $s->motivo_detalle,
                'usuario' => $s->solicitante->name . ' ' . $s->solicitante->last_name,
                'usuario_rol' => $s->solicitante->role->display_name ?? 'N/A',
            ]);

            $historialMovimientos = $fondo->historialMovimientos->map(fn($m) => [
                'tipo' => $m->tipo_movimiento,
                'fecha' => $m->fecha_movimiento,
                'monto' => $m->monto_movimiento,
                'motivo' => $m->comentario,
                'usuario' => $m->usuarioAccion->name . ' ' . $m->usuarioAccion->last_name,
                'usuario_rol' => $m->usuarioAccion->role->display_name ?? 'N/A',
            ]);

            $timelineCompleta = $historialSolicitudes->concat($historialMovimientos)->sortByDesc('fecha')->take(4)->values();

            $timelines[] = ['codigo_fondo' => $fondo->codigo_fondo, 'eventos' => $timelineCompleta];
        }
        return $timelines;
    }
    private function calculateTopAreasPorGasto(Collection $gastos): Collection
    {
        return $gastos->whereIn('estado', ['Contabilizado', 'Repuesto'])
            ->groupBy(fn($g) => $g->fondoEfectivo->area->name ?? 'Sin Área')
            ->map(fn(Collection $group) => (float) $group->sum('monto_total'))
            ->sortDesc()
            ->take(5)
            ->map(fn($total, $area) => ['area' => $area, 'total' => $total])
            ->values();
    }

    /** Helper para aplicar scopes de forma centralizada en las queries históricas */
    private function applyScopeToQuery($query, User $user, array $filters, string $type)
    {
        if ($user->isJefeArea() || $user->isColaborador()) {
            $areaId = $user->area_id;
            $type === 'gasto' ? $query->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $areaId)) : $query->where('id_area', $areaId);
        } elseif ($user->hasAnyRole(['super_admin', 'jefe_administracion', 'gerente_general'])) {
            if (!empty($filters['area_id'])) {
                $areaId = $filters['area_id'];
                $type === 'gasto' ? $query->whereHas('fondoEfectivo', fn($q) => $q->where('id_area', $areaId)) : $query->where('id_area', $areaId);
            }
            if (!empty($filters['responsable_id']) && $type === 'gasto') {
                $query->where('id_registrador', $filters['responsable_id']);
            }
        }
    }
}
