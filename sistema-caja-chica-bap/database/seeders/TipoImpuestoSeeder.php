<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoImpuesto;

class TipoImpuestoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tiposDeImpuesto = [
            [
                'nombre' => 'IGV',
                'porcentaje' => 18.00,
                'factor_calculo' => 1.18
            ],
            [
                'nombre' => 'IGV10',
                'porcentaje' => 10.00,
                'factor_calculo' => 1.10
            ],
            [
                'nombre' => 'EXO',
                'porcentaje' => 0.00,
                'factor_calculo' => 1.00
            ],
        ];

        foreach ($tiposDeImpuesto as $tipo) {
            TipoImpuesto::updateOrCreate(
                ['nombre' => $tipo['nombre']],
                [
                    'porcentaje' => $tipo['porcentaje'],
                    'factor_calculo' => $tipo['factor_calculo']
                ]
            );
        }
    }
}
