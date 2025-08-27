<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ClasificacionBienServicio;

class ClasificacionBienServicioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clasificaciones = [
            ['codigo' => '1', 'nombre' => 'Mercaderia, Materia Prima, Suministro, Envases Y Embalajes'],
            ['codigo' => '2', 'nombre' => 'Activo Fijo'],
            ['codigo' => '3', 'nombre' => 'Otros Activos No Considerados En Los Numerales 1 Y 2'],
            ['codigo' => '4', 'nombre' => 'Gastos De Educación, Recreación, Salud, Culturales. Representación, Capacitación, Viaje, Mant Vehícular'],
            ['codigo' => '5', 'nombre' => 'Otros Gastos No Incluidos En El Numeral 4'],
        ];

        foreach ($clasificaciones as $clasificacion) {
            ClasificacionBienServicio::updateOrCreate(
                ['codigo' => $clasificacion['codigo']],
                ['nombre' => $clasificacion['nombre']]
            );
        }
    }
}
