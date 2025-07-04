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
        // Limpieza segura de las tablas
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Proyecto::truncate();
        DB::table('area_proyecto')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

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

        // --- INICIO DE CORRECCIÓN ---
        // Se asocian las áreas de una forma más segura.

        // 1. Obtener las áreas por su acrónimo.
        $acronimosProyecto1 = ['GSO', 'LOG', 'PRY', 'PRO'];
        $acronimosProyecto2 = ['GSO', 'AYE'];

        $areasProyecto1 = Area::whereIn('acronym', $acronimosProyecto1)->get();
        $areasProyecto2 = Area::whereIn('acronym', $acronimosProyecto2)->get();
        
        // 2. Asociar áreas al proyecto "Fuertes como el hierro" solo si se encontraron áreas.
        if ($proyecto1 && $areasProyecto1->isNotEmpty()) {
            // Usamos pluck('id') para obtener solo los IDs de las áreas encontradas.
            $proyecto1->areas()->attach($areasProyecto1->pluck('id'));
            $this->command->info($areasProyecto1->count() . ' áreas asociadas a "Fuertes como el hierro".');
        } else {
            $this->command->warn('No se asociaron áreas a "Fuertes como el hierro" porque no se encontraron las áreas con los acrónimos especificados.');
        }

        // 3. Asociar áreas al proyecto "Cucharones Luchadores"
        if ($proyecto2 && $areasProyecto2->isNotEmpty()) {
            $proyecto2->areas()->attach($areasProyecto2->pluck('id'));
            $this->command->info($areasProyecto2->count() . ' áreas asociadas a "Cucharones Luchadores".');
        } else {
            $this->command->warn('No se asociaron áreas a "Cucharones Luchadores" porque no se encontraron las áreas con los acrónimos especificados.');
        }
        // --- FIN DE CORRECCIÓN ---
    }
}
