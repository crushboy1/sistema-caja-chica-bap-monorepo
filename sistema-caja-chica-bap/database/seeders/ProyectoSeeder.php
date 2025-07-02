<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Proyecto;
use App\Models\Area;

class ProyectoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Desactiva temporalmente la revisión de claves foráneas para evitar problemas de orden.
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Limpia la tabla antes de poblarla para evitar duplicados en ejecuciones repetidas.
        Proyecto::truncate();
        DB::table('area_proyecto')->truncate();

        // Crear los proyectos
        $proyecto1 = Proyecto::create([
            'nombre_proyecto' => 'Fuertes como el hierro',
            'descripcion' => 'Proyecto para combatir la anemia y la desnutrición infantil.',
            'presupuesto' => 50000.00,
            'fecha_inicio' => '2025-01-15',
            'activo' => true,
        ]);

        $proyecto2 = Proyecto::create([
            'nombre_proyecto' => 'Cucharones Luchadores',
            'descripcion' => 'Iniciativa para apoyar y capacitar a comedores populares.',
            'presupuesto' => 35000.00,
            'fecha_inicio' => '2025-03-01',
            'activo' => true,
        ]);

        $this->command->info('Proyectos creados exitosamente.');

        // --- ASOCIACIÓN DE ÁREAS A PROYECTOS ---
        // Obtener las áreas por su nombre o acrónimo. Es más robusto que usar IDs fijos.
        $areaGestionSocial = Area::where('acronym', 'GSO')->first();
        $areaLogistica = Area::where('acronym', 'LOG')->first();
        $areaProyectos = Area::where('acronym', 'PRY')->first();
        $areaCalidad = Area::where('acronym', 'PRO')->first(); // Asumiendo que 'PRO' es de Procesos/Calidad
        $areaAlianzas = Area::where('acronym', 'AYE')->first();

        
        if ($proyecto1 && $areaGestionSocial && $areaLogistica && $areaProyectos && $areaCalidad) {
            $proyecto1->areas()->attach([
                $areaGestionSocial->id_area,
                $areaLogistica->id_area,
                $areaProyectos->id_area,
                $areaCalidad->id_area,
            ]);
            $this->command->info('Áreas asociadas a "Fuertes como el hierro".');
        }

        // Asociar áreas al proyecto "Cucharones Luchadores" (Ejemplo)
        if ($proyecto2 && $areaGestionSocial && $areaAlianzas) {
            $proyecto2->areas()->attach([
                $areaGestionSocial->id_area,
                $areaAlianzas->id_area,
            ]);
            $this->command->info('Áreas asociadas a "Cucharones Luchadores".');
        }
        
        // Reactiva la revisión de claves foráneas.
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}
