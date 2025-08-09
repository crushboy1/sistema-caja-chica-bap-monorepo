<?php

namespace App\Exports;

use App\Models\ActivityLog;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ActivityLogExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    /**
     * Se añade una propiedad para almacenar los filtros
     * que se reciben desde el controlador.
     */
    protected $filters;

    /**
     * El constructor recibe los filtros y los guarda
     * para usarlos en la consulta a la base de datos.
     */
    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    /**
     *  Se implementa el método `query()` en lugar de `collection()`.
     * Esto es mucho más eficiente para la memoria, ya que procesa los resultados
     * de la base de datos en lotes en lugar de cargar todo en un solo array.
     */
    public function query()
    {
        $query = ActivityLog::query()->with('user:id,name,last_name');

        if (!empty($this->filters['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', $this->filters['fecha_inicio']);
        }
        if (!empty($this->filters['fecha_fin'])) {
            $query->whereDate('created_at', '<=', $this->filters['fecha_fin']);
        }
        if (!empty($this->filters['user_id'])) {
            $query->where('user_id', $this->filters['user_id']);
        }
        if (!empty($this->filters['subject_type'])) {
            $query->where('subject_type', 'App\\Models\\' . $this->filters['subject_type']);
        }

        return $query->latest();
    }

    /**
     * Se implementa el método `headings()` para definir los
     * encabezados de las columnas en el archivo Excel.
     */
    public function headings(): array
    {
        return [
            "ID Log",
            "Fecha y Hora",
            "Usuario",
            "Acción",
            "Módulo Afectado",
            "ID del Registro Afectado",
            "Detalle Principal",
            "Cambios (JSON)",
        ];
    }

    /**
     * Se implementa el método `map()` para transformar cada
     * registro del log en el formato de fila que queremos para el Excel.
     *
     * @param ActivityLog $log
     * @return array
     */
    public function map($log): array
    {
        return [
            $log->id,
            $log->created_at->format('d/m/Y H:i:s'),
            $log->user ? $log->user->name . ' ' . $log->user->last_name : 'Sistema',
            str_replace('_', ' ', $log->action_type),
            $log->subject_type ? last(explode('\\', $log->subject_type)) : 'N/A',
            $log->subject_id,
            $log->properties['descripcion'] ?? '',
            // Se exportan los detalles completos en formato JSON para un análisis más profundo si es necesario.
            json_encode($log->properties),
        ];
    }
}
