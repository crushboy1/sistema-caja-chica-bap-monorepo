<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class AreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define las áreas a insertar
        $areas = [
            ['name' => 'Administración y Contabilidad', 'description' => 'Área encargada de la gestión administrativa.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Estrategia y Alianzas', 'description' => 'Área encargada de la gestión de convenios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Gestión y Proyección Social', 'description' => 'Área que gestiona los programas de ayuda y distribución.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Voluntariado', 'description' => 'Área encargada de la gestión de voluntarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Logística', 'description' => 'Área encargada de la cadena de suministro y transporte.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Tecnología de la Información', 'description' => 'Área encargada de los sistemas y la infraestructura tecnológica.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Calidad y Procesos', 'description' => 'Área encargada de los procesos de la organización.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Gerencia General', 'description' => 'Area que encargada de tomar decisiones relevantes para la organización.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            // Puedes añadir más áreas según las necesidades del BAP
        ];
        // Inserta los datos en la tabla 'areas'
        DB::table('areas')->insert($areas);
    }
}
