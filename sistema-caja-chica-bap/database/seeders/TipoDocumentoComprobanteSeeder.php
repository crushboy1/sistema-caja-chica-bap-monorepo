<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoDocumentoComprobante;

class TipoDocumentoComprobanteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposDeDocumento = [
            ['codigo_comprobante' => '00', 'nombre' => 'Otros (Declaración Jurada)'],
            ['codigo_comprobante' => '01', 'nombre' => 'Factura'],
            ['codigo_comprobante' => '02', 'nombre' => 'Recibo por Honorarios'],
            ['codigo_comprobante' => '03', 'nombre' => 'Boleta de Venta'],
            ['codigo_comprobante' => '05', 'nombre' => 'Boleto aéreo'],
            ['codigo_comprobante' => '07', 'nombre' => 'Nota de Crédito'],
            ['codigo_comprobante' => '08', 'nombre' => 'Nota de Débito'],
            ['codigo_comprobante' => '10', 'nombre' => 'Recibo por Arrendamiento'],
            ['codigo_comprobante' => '12', 'nombre' => 'Ticket de Máquina Registradora'],
            ['codigo_comprobante' => '14', 'nombre' => 'Recibo por servicios públicos'],
            ['codigo_comprobante' => '91', 'nombre' => 'Invoice'],
        ];

        foreach ($tiposDeDocumento as $tipo) {
            TipoDocumentoComprobante::updateOrCreate(
                ['codigo_comprobante' => $tipo['codigo_comprobante']],
                ['nombre' => $tipo['nombre']]
            );
        }
    }
}
