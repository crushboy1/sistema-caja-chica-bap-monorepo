<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\CentroCosto;
use Carbon\Carbon;

class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Este seeder actualiza las áreas existentes o crea nuevas,
     * asignando el ID del centro de costo correspondiente.
     *
     * @return void
     */
    public function run(): void
    {
        // 1. Obtenemos un mapa de los códigos de centro de costo a sus IDs.
        $centrosCostoMap = CentroCosto::pluck('id', 'codigo');

        $areasData = [
            ['name' => 'Administración', 'acronym' => 'ADM', 'description' => 'Área de gestión administrativa.', 'centro_costo_codigo' => 'C01'],
            ['name' => 'Estrategia (Gestion Corporativa)', 'acronym' => 'EST', 'description' => 'Área de alianzas estratégicas.', 'centro_costo_codigo' => 'C02'],
            ['name' => 'Logística', 'acronym' => 'LOG', 'description' => 'Área de cadena de suministro.', 'centro_costo_codigo' => 'C03'],
            ['name' => 'Gestión Social', 'acronym' => 'GSO', 'description' => 'Área de programas de ayuda.', 'centro_costo_codigo' => 'C04'],
            ['name' => 'Comunicaciones', 'acronym' => 'COM', 'description' => 'Área de comunicación y marketing.', 'centro_costo_codigo' => 'C05'],
            ['name' => 'G. Operaciones', 'acronym' => 'OPE', 'description' => 'Área de operaciones generales.', 'centro_costo_codigo' => 'C06'],
            ['name' => 'Gerencia General', 'acronym' => 'GG', 'description' => 'Área de toma de decisiones.', 'centro_costo_codigo' => 'G07'],
            ['name' => 'Proyectos', 'acronym' => 'PRY', 'description' => 'Área de gestión de proyectos.', 'centro_costo_codigo' => 'G08'],
            ['name' => 'Calidad y Procesos', 'acronym' => 'PRO', 'description' => 'Área de optimización de procesos.', 'centro_costo_codigo' => 'C09'],
            ['name' => 'Comercial y Fondos (Fundraising)', 'acronym' => 'CYF', 'description' => 'Área de recaudación de fondos.', 'centro_costo_codigo' => 'C11'],
            ['name' => 'Voluntariado', 'acronym' => 'VOL', 'description' => 'Área de gestión de voluntarios.', 'centro_costo_codigo' => 'C12'],
            ['name' => 'Tecnología de la Información', 'acronym' => 'TI', 'description' => 'Área de sistemas.', 'centro_costo_codigo' => null],
        ];
        foreach ($areasData as $areaData) {
            // 2. Buscamos el ID del centro de costo usando el mapa.
            $centroCostoId = $centrosCostoMap[$areaData['centro_costo_codigo']] ?? null;

            // 3. Usamos updateOrInsert para no borrar datos existentes.
            Area::updateOrInsert(
                ['name' => $areaData['name']], // Condición para buscar el área
                [
                    'acronym' => $areaData['acronym'],
                    'description' => $areaData['description'],
                    'centro_costo_id' => $centroCostoId,
                    'activo' => true,
                    'updated_at' => Carbon::now(),
                ]
            );
        }
    }
}
