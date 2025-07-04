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

        $now = Carbon::now();

        $areas = [
            [
                'name' => 'Gestión Social',
                'acronym' => 'GSO',
                'description' => 'Área que gestiona los programas de ayuda y distribución.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Proyectos',
                'acronym' => 'PRY',
                'description' => 'Área encargada de la gestión y ejecución de proyectos.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Logística',
                'acronym' => 'LOG',
                'description' => 'Área encargada de la cadena de suministro y transporte.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Procesos',
                'acronym' => 'PRO',
                'description' => 'Área encargada de la optimización de procesos de la organización.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Alianzas y Estrategias',
                'acronym' => 'AYE',
                'description' => 'Área encargada de la gestión de convenios y alianzas estratégicas.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Administración',
                'acronym' => 'ADM',
                'description' => 'Área encargada de la gestión administrativa y contable.',
                'created_at' => $now, 'updated_at' => $now
            ],
            [
                'name' => 'Gerencia General',
                'acronym' => 'GG',
                'description' => 'Área que encargada de tomar decisiones relevantes para la organización.',
                'created_at' => $now, 'updated_at' => $now
            ],
             // Puedes añadir otras áreas si es necesario, como TI, Voluntariado, etc.
            [
                'name' => 'Tecnología de la Información',
                'acronym' => 'TI',
                'description' => 'Área encargada de los sistemas y la infraestructura tecnológica.',
                'created_at' => $now, 'updated_at' => $now
            ],
        ];

        // Inserta los datos en la tabla 'areas'
        DB::table('areas')->insert($areas);
    }
}
