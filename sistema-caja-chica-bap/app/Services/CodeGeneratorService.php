<?php

namespace App\Services;

use App\Models\SolicitudFondo;
use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    /**
     * Genera un código único para una nueva solicitud de fondo.
     * Formato: ÁREA-ACTIVIDAD-TIPOFONDO-CORRELATIVO (ej: GSO-SOL-01-01)
     *
     * @param SolicitudFondo $solicitud El modelo de la solicitud que se está creando.
     * @return string El código generado.
     */
    public function generateForSolicitud(SolicitudFondo $solicitud): string
    {
        // 1. Obtener Acrónimo del Área
        $areaAcronym = $solicitud->area->acronym ?? 'XXX';

        // 2. Obtener Código de Actividad
        // Para este modelo, la actividad siempre es 'SOL' (Solicitud).
        $activityCode = 'SOL';

        // 3. Obtener Código del Tipo de Fondo
        $fundTypeCode = '00'; // Valor por defecto
        if ($solicitud->tipo_solicitud === 'Apertura') {
            // Si es una nueva apertura, tomamos el tipo de la solicitud misma.
            $fundTypeCode = $this->getFundTypeCode($solicitud->tipo_fondo_solicitado);
        } else if ($solicitud->solicitudOriginal && $solicitud->solicitudOriginal->fondoEfectivo) {
            // Si es una modificación, buscamos el tipo en el fondo original que se está modificando.
            $fundTypeCode = $this->getFundTypeCode($solicitud->solicitudOriginal->fondoEfectivo->tipo_fondo);
        }

        // 4. Generar Correlativo
        $prefix = "{$areaAcronym}-{$activityCode}-{$fundTypeCode}-";

        // Buscamos el último código con el mismo prefijo para determinar el siguiente número.
        $latestCode = SolicitudFondo::where('codigo_solicitud', 'like', $prefix . '%')
            ->lockForUpdate() // Bloquea la fila para evitar condiciones de carrera si dos solicitudes se crean al mismo tiempo.
            ->orderBy('id', 'desc') // Ordenar por ID para mayor fiabilidad
            ->value('codigo_solicitud');

        $correlative = 1;
        if ($latestCode) {
            $lastNumber = (int) substr($latestCode, strlen($prefix));
            $correlative = $lastNumber + 1;
        }

        // Formateamos el correlativo a 2 dígitos con ceros a la izquierda (ej: 01, 02, ... 10).
        return $prefix . str_pad($correlative, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Mapea el nombre del tipo de fondo a su código numérico.
     *
     * @param string|null $tipoFondo
     * @return string
     */
    private function getFundTypeCode(?string $tipoFondo): string
    {
        return match ($tipoFondo) {
            'Regular' => '01',
            'Proyecto' => '02',
            'Excepcional' => '03',
            default => '00',
        };
    }
}
