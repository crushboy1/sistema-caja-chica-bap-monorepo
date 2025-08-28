<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CentroCosto;
use Illuminate\Support\Facades\DB;

class CentroCostoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        // Usamos truncate para un reinicio limpio del catálogo maestro.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        CentroCosto::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $centrosCosto = [
            ['codigo' => 'C01', 'descripcion' => 'Administración'],
            ['codigo' => 'C02', 'descripcion' => 'Estrategia (Gestion Corporativa)'],
            ['codigo' => 'C03', 'descripcion' => 'Logistica'],
            ['codigo' => 'C04', 'descripcion' => 'Gestión Social'],
            ['codigo' => 'C05', 'descripcion' => 'Comunicaciones'],
            ['codigo' => 'C06', 'descripcion' => 'G. Operaciones', 'activo' => false],
            ['codigo' => 'G07', 'descripcion' => 'Gerente General'],
            ['codigo' => 'G08', 'descripcion' => 'Proyectos'],
            ['codigo' => 'C09', 'descripcion' => 'Calidad y Procesos'],
            ['codigo' => 'C11', 'descripcion' => 'Comercial y Fondos (Fundraising)'],
            ['codigo' => 'C12', 'descripcion' => 'Voluntariado'],
        ];

        foreach ($centrosCosto as $centro) {
            CentroCosto::create($centro);
        }
    }
}
