<?php

namespace App\Exports;

use App\Models\ActivityLog;
use App\Traits\FiltersActivityLog;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Carbon\Carbon;

class EnhancedActivityLogExport implements WithMultipleSheets
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Define las múltiples hojas del Excel
     */
    public function sheets(): array
    {
        return [
            new ActivityLogMainSheet($this->filters),
            new ActivityLogStatsSheet($this->filters),
            new ActivityLogSummarySheet($this->filters),
        ];
    }
}

/**
 * Hoja principal con el detalle de todos los logs
 */
class ActivityLogMainSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    use FiltersActivityLog;

    protected $filters;
    protected $rowNumber = 1;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $request = new \Illuminate\Http\Request($this->filters);

        return $this->buildFilteredQuery($request)
            ->with('user:id,name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function headings(): array
    {
        return [
            'N°',
            'Fecha y Hora',
            'Usuario',
            'Acción Realizada',
            'Módulo del Sistema',
            'Detalle de la Operación',
            'Cambios Específicos'
        ];
    }

    public function map($log): array
    {
        return [
            $this->rowNumber++,
            Carbon::parse($log->created_at)->format('d/m/Y H:i:s'),
            $log->user ? trim($log->user->name . ' ' . $log->user->last_name) : 'Sistema Automático',
            $this->formatActionType($log->action_type),
            $this->formatSubjectType($log->subject_type),
            $this->generateDetailDescription($log),
            $this->formatChangesDetailed($log->properties)
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5B4A']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
            ],
            'A:G' => [
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ]
            ]
        ];
    }

    public function title(): string
    {
        return 'Detalle de Auditoría';
    }

    // Métodos de formateo (mantener igual que en la segunda versión)
    private function formatActionType(string $actionType): string
    {
        $actions = [
            'CREADO' => 'Nuevo Registro Creado',
            'ACTUALIZADO' => 'Información Modificada',
            'ELIMINADO' => 'Registro Eliminado',
            'PERIODO_CERRADO' => 'Cierre de Período',
            'PERIODO_REABIERTO' => 'Reapertura de Período',
            'EXCEPCION_OTORGADA' => 'Excepción Autorizada',
            'EXCEPCION_REVOCADA' => 'Excepción Revocada'
        ];
        return $actions[$actionType] ?? $actionType;
    }

    private function formatSubjectType(string $subjectType): string
    {
        // Usamos getModelFriendlyName del trait para mantener la consistencia
        return $this->getModelFriendlyName($subjectType);
    }

    private function generateDetailDescription($log): string
    {
        if (isset($log->properties['descripcion'])) {
            return $log->properties['descripcion'];
        }

        $modelName = $this->formatSubjectType($log->subject_type);
        $actionType = $log->action_type;

        switch ($actionType) {
            case 'CREADO':
                return "Se creó un nuevo elemento en {$modelName}";

            case 'ACTUALIZADO':
                $changedFields = $this->getChangedFieldsNames($log->properties);
                if (!empty($changedFields)) {
                    return "Se modificaron: " . implode(', ', $changedFields);
                }
                return "Se actualizó información en {$modelName}";

            case 'ELIMINADO':
                return "Se eliminó un registro de {$modelName}";

            case 'PERIODO_CERRADO':
                return "Se cerró el período contable";

            case 'PERIODO_REABIERTO':
                return "Se reabrió el período contable";

            default:
                return "Operación {$this->formatActionType($actionType)} en {$modelName}";
        }
    }

    /**
     * Obtiene los nombres amigables de los campos que cambiaron
     */
    private function getChangedFieldsNames($properties): array
    {
        if (!isset($properties['changed']) || !is_array($properties['changed'])) {
            return [];
        }

        $changedFieldNames = [];
        foreach (array_keys($properties['changed']) as $field) {
            $changedFieldNames[] = $this->getFieldFriendlyName($field);
        }

        return $changedFieldNames;
    }

    private function formatChangesSimple($properties): string
    {
        if (!isset($properties['changed'])) {
            return 'Sin cambios específicos';
        }

        $changedCount = count($properties['changed']);
        if ($changedCount == 0) return 'Sin cambios';
        if ($changedCount == 1) return '1 campo modificado';

        return "{$changedCount} campos modificados";
    }

    /**
     * Formatea los cambios de manera detallada y comprensible
     */
    private function formatChangesDetailed($properties): string
    {
        if (!isset($properties['changed']) || !is_array($properties['changed'])) {
            return 'Sin cambios registrados';
        }

        $changes = [];

        foreach ($properties['changed'] as $field => $newValue) {
            $oldValue = $properties['old'][$field] ?? 'N/A';

            // Formatear valores especiales
            $oldFormatted = $this->formatValue($field, $oldValue);
            $newFormatted = $this->formatValue($field, $newValue);

            // Obtener nombre amigable del campo
            $fieldName = $this->getFieldFriendlyName($field);

            $changes[] = "{$fieldName}: '{$oldFormatted}' → '{$newFormatted}'";
        }

        return implode(" | ", $changes); // Usar | en lugar de \n para Excel
    }

    /**
     * Obtiene el nombre amigable de un campo
     */
    private function getFieldFriendlyName(string $field): string
    {
        $fieldNames = [
            'name' => 'Nombre',
            'nombre' => 'Nombre',
            'descripcion' => 'Descripción',
            'codigo' => 'Código',
            'activo' => 'Estado',
            'monto' => 'Monto',
            'fecha' => 'Fecha',
            'estado' => 'Estado',
            'created_at' => 'Creado el',
            'updated_at' => 'Actualizado el',
            'id_proyecto' => 'Proyecto',
            'id_cuenta_contable' => 'Cuenta Contable'
        ];

        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Formatea valores específicos para que sean más legibles
     */
    private function formatValue(string $field, $value): string
    {
        if ($value === null) return 'Sin valor';
        if ($value === '') return 'Vacío';

        switch ($field) {
            case 'activo':
                return $value ? 'Activo' : 'Inactivo';

            case 'created_at':
            case 'updated_at':
                try {
                    return Carbon::parse($value)->format('d/m/Y H:i');
                } catch (\Exception $e) {
                    return $value;
                }

            case 'monto':
                return is_numeric($value) ? 'S/ ' . number_format($value, 2) : $value;

            default:
                return (string) $value;
        }
    }
}

/**
 * Hoja con estadísticas y resumen
 */
class ActivityLogStatsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    use FiltersActivityLog;

    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $request = new \Illuminate\Http\Request($this->filters);
        $baseQuery = $this->buildFilteredQuery($request);

        $statsByAction = (clone $baseQuery)->selectRaw('action_type, COUNT(*) as total')->groupBy('action_type')->get();
        $statsByModule = (clone $baseQuery)->selectRaw('subject_type, COUNT(*) as total')->groupBy('subject_type')->get();

        $stats = collect();
        $stats->push((object)['category' => 'RESUMEN POR TIPO DE ACCIÓN', 'item' => '', 'count' => '', 'percentage' => '']);
        $totalActions = $statsByAction->sum('total');
        foreach ($statsByAction as $stat) {
            $percentage = $totalActions > 0 ? round(($stat->total / $totalActions) * 100, 1) : 0;
            $stats->push((object)['category' => 'Tipo de Acción', 'item' => $this->formatActionType($stat->action_type), 'count' => $stat->total, 'percentage' => $percentage . '%']);
        }
        $stats->push((object)['category' => '', 'item' => '', 'count' => '', 'percentage' => '']);
        $stats->push((object)['category' => 'RESUMEN POR MÓDULO DEL SISTEMA', 'item' => '', 'count' => '', 'percentage' => '']);
        foreach ($statsByModule as $stat) {
            $percentage = $totalActions > 0 ? round(($stat->total / $totalActions) * 100, 1) : 0;
            $stats->push((object)['category' => 'Módulo', 'item' => $this->getModelFriendlyName($stat->subject_type), 'count' => $stat->total, 'percentage' => $percentage . '%']);
        }
        return $stats;
    }

    public function headings(): array
    {
        return [
            'Categoría',
            'Elemento',
            'Cantidad',
            'Porcentaje'
        ];
    }

    public function map($stat): array
    {
        return [
            $stat->category,
            $stat->item,
            $stat->count,
            $stat->percentage
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5B4A']],
            ]
        ];
    }

    public function title(): string
    {
        return 'Estadísticas';
    }

    private function formatActionType(string $actionType): string
    {
        $actions = [
            'CREADO' => 'Registros Creados',
            'ACTUALIZADO' => 'Modificaciones',
            'ELIMINADO' => 'Eliminaciones',
            'PERIODO_CERRADO' => 'Cierres de Período',
            'PERIODO_REABIERTO' => 'Reaperturas',
        ];
        return $actions[$actionType] ?? $actionType;
    }

    private function formatSubjectType(string $subjectType): string
    {
        $modules = [
            'Proyecto' => 'Gestión de Proyectos',
            'GastoProyectado' => 'Presupuesto y Gastos',
            'CuentaContable' => 'Plan de Cuentas',
            'CierreMensual' => 'Control de Períodos'
        ];
        return $modules[class_basename($subjectType)] ?? class_basename($subjectType);
    }
}

/**
 * Hoja con información general del reporte
 */
class ActivityLogSummarySheet implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $info = collect();

        $info->push((object)[
            'field' => 'Reporte Generado el:',
            'value' => Carbon::now()->format('d/m/Y H:i:s')
        ]);

        $info->push((object)[
            'field' => 'Generado por:',
            'value' => auth()->user() ? auth()->user()->name . ' ' . auth()->user()->last_name : 'Sistema'
        ]);

        if (!empty($this->filters['fecha_inicio'])) {
            $info->push((object)[
                'field' => 'Fecha desde:',
                'value' => Carbon::parse($this->filters['fecha_inicio'])->format('d/m/Y')
            ]);
        }

        if (!empty($this->filters['fecha_fin'])) {
            $info->push((object)[
                'field' => 'Fecha hasta:',
                'value' => Carbon::parse($this->filters['fecha_fin'])->format('d/m/Y')
            ]);
        }

        return $info;
    }

    public function headings(): array
    {
        return ['Información del Reporte', 'Valor'];
    }

    public function map($info): array
    {
        return [$info->field, $info->value];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2D5B4A']],
            ]
        ];
    }

    public function title(): string
    {
        return 'Información del Reporte';
    }
}
