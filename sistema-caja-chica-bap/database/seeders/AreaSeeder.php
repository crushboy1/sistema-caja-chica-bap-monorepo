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
     *
     * @return void
     */
    public function run(): void
    {
        // Se limpia la tabla antes de insertar para evitar duplicados en re-ejecuciones.
        DB::table('areas')->delete();

        $areas = [
            [
                'name' => 'Administración y Contabilidad',
                'acronym' => 'AC', // Acrónimo añadido
                'description' => 'Área encargada de la gestión administrativa.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Estrategia y Alianzas',
                'acronym' => 'EA', // Acrónimo añadido
                'description' => 'Área encargada de la gestión de convenios.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Gestión y Proyección Social',
                'acronym' => 'GPS', // Acrónimo añadido
                'description' => 'Área que gestiona los programas de ayuda y distribución.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Voluntariado',
                'acronym' => 'VOL', // Acrónimo añadido
                'description' => 'Área encargada de la gestión de voluntarios.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Logística',
                'acronym' => 'LOG', // Acrónimo añadido
                'description' => 'Área encargada de la cadena de suministro y transporte.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Tecnología de la Información',
                'acronym' => 'TI', // Acrónimo añadido
                'description' => 'Área encargada de los sistemas y la infraestructura tecnológica.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Calidad y Procesos',
                'acronym' => 'CP', // Acrónimo añadido
                'description' => 'Área encargada de los procesos de la organización.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
            [
                'name' => 'Gerencia General',
                'acronym' => 'GG', // Acrónimo añadido
                'description' => 'Área que encargada de tomar decisiones relevantes para la organización.',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ],
        ];

        // Inserta los datos en la tabla 'areas'
        DB::table('areas')->insert($areas);
    }
}
