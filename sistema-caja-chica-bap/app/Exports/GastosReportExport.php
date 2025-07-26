<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize; // Opcional: para autoajustar el ancho de las columnas

class GastosReportExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = new Collection($data);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->data;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        // Define los encabezados de tu archivo Excel.
        // Deben coincidir con las claves de los arrays que pasas en el constructor.
        if ($this->data->isEmpty()) {
            return [];
        }
        // Tomar las claves del primer elemento como encabezados
        return array_keys($this->data->first()->toArray());
    }
}
