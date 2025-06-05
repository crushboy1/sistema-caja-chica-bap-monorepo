<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
class TipoDocumentoIdentidadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define los tipos de documentos de identidad a insertar
        $tipos = [
            ['name' => 'DNI', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'RUC', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Carnet de Extranjería', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'Pasaporte', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            // Puedes añadir más tipos si es necesario
        ];
        // Inserta los datos en la tabla 'tipo_documentos_identidad'
        DB::table('tipo_documentos_identidad')->insert($tipos);
    }
}
