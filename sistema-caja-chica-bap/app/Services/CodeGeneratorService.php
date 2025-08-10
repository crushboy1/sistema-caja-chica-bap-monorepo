<?php

namespace App\Services;

use App\Models\FondoEfectivo;
use App\Models\Gasto;
use App\Models\SolicitudFondo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    /**
     * Mapeo de tipos de actividad a sus acrónimos.
     * @var array
     */
    private const ACTIVITY_CODES = [
        'Apertura' => 'SOL',
        'Incremento' => 'MOD',
        'Decremento' => 'MOD',
        'Cierre' => 'MOD',
        'Declaracion' => 'DEC',
        'Fondo' => 'FON',
    ];

    /**
     * Mapeo de tipos de fondo a sus códigos numéricos.
     * @var array
     */
    private const FUND_TYPE_CODES = [
        'Regular' => '01',
        'Proyecto' => '02',
        'Excepcional' => '03',
    ];

    /**
     * Genera un código para una Solicitud de Fondo (SOL o MOD),
     * manteniendo la lógica original de obtención de datos.
     */
    public function generateForSolicitud(SolicitudFondo $solicitud): string
    {
        // 1. Obtener Acrónimo del Área
        $areaAcronym = $solicitud->area->acronym ?? 'XXX';

        // 2. Obtener Código de Actividad (SOL vs MOD)
        $activityCode = self::ACTIVITY_CODES[$solicitud->tipo_solicitud] ?? 'SOL';

        // 3. Obtener Código del Tipo de Fondo (Lógica original preservada)
        $fundTypeCode = '00'; // Valor por defecto
        if ($solicitud->tipo_solicitud === 'Apertura') {
            $fundTypeCode = self::FUND_TYPE_CODES[$solicitud->tipo_fondo_solicitado] ?? '00';
        } else if ($solicitud->solicitudOriginal && $solicitud->solicitudOriginal->fondoEfectivo) {
            $fundTypeCode = self::FUND_TYPE_CODES[$solicitud->solicitudOriginal->fondoEfectivo->tipo_fondo] ?? '00';
        }

        // 4. Construir prefijo y generar código
        $prefix = "{$areaAcronym}-{$activityCode}-{$fundTypeCode}-";
        return $this->generateCode(SolicitudFondo::class, 'codigo_solicitud', $prefix);
    }

    /**
     * Genera un código para un Gasto individual (DEC).
     */
    public function generateForGasto(Gasto $gasto): string
    {
        $areaAcronym = $gasto->fondoEfectivo->area->acronym ?? 'XXX';
        $activityCode = self::ACTIVITY_CODES['Declaracion'];
        $fundTypeCode = self::FUND_TYPE_CODES[$gasto->fondoEfectivo->tipo_fondo] ?? '00';

        $prefix = "{$areaAcronym}-{$activityCode}-{$fundTypeCode}-";

        return $this->generateCode(Gasto::class, 'codigo_gasto', $prefix);
    }

    /**
     * Genera un código para un Fondo de Efectivo (FON).
     */
    public function generateForFondo(FondoEfectivo $fondo): string
    {
        $areaAcronym = $fondo->area->acronym ?? 'XXX';
        $activityCode = self::ACTIVITY_CODES['Fondo'];
        $fundTypeCode = self::FUND_TYPE_CODES[$fondo->tipo_fondo] ?? '00';

        $prefix = "{$areaAcronym}-{$activityCode}-{$fundTypeCode}-";

        return $this->generateCode(FondoEfectivo::class, 'codigo_fondo', $prefix);
    }

    /**
     * Lógica central para generar un código correlativo único y seguro.
     *
     * @param string $modelClass El nombre de la clase del modelo (ej: SolicitudFondo::class).
     * @param string $columnName El nombre de la columna donde se guarda el código.
     * @param string $prefix El prefijo del código (ej: "GSO-SOL-01-").
     * @return string El código completo generado.
     */
    private function generateCode(string $modelClass, string $columnName, string $prefix): string
    {
        $modelInstance = new $modelClass();
        $primaryKey = $modelInstance->getKeyName(); // Obtiene la llave primaria ('id', 'id_fondo', etc.) dinámicamente

        $latestCode = DB::table($modelInstance->getTable())
            ->where($columnName, 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderBy($primaryKey, 'desc') // Usa la llave primaria dinámica para ordenar
            ->value($columnName);

        $correlative = 1;
        if ($latestCode) {
            $lastNumber = (int) substr($latestCode, strlen($prefix));
            $correlative = $lastNumber + 1;
        }

        // Formato a 4 dígitos para soportar hasta 9999 registros por prefijo.
        return $prefix . str_pad($correlative, 4, '0', STR_PAD_LEFT);
    }
}
