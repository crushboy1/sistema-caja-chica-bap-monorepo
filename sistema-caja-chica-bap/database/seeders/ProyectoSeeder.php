<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Proyecto; // Es buena práctica usar el modelo

class ProyectoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * [CAMBIO] Seeder actualizado con la lista oficial de proyectos de BAP.
     * Se eliminó la lógica de asociación de áreas, ya que se manejará en otro lugar si es necesario.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Proyecto::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = Carbon::now();

        $proyectos = [
            ['codigo' => 'AGRO', 'nombre' => 'RESCATE DEL AGRO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ALIMENTATON 2023', 'nombre' => 'ALIMENTATON 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ALIMENTATON 2024', 'nombre' => 'ALIMENTATON 2024', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ALIMENTATON 2025', 'nombre' => 'ALIMENTATON 2025', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ALMABSF', 'nombre' => 'ALMACEN PUNTA HERMOSA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ALMACEN', 'nombre' => 'ALMACENES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'APORTE SOLIDARIO', 'nombre' => 'APORTE SOLIDARIO ORG SOCIALES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'BAP', 'nombre' => 'BANCO DE ALIMENTOS', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'BASE DE DATOS 2025', 'nombre' => 'BASE DE DATOS 2025', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'BICENTENARIO', 'nombre' => 'BICENTENARIO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'BURGER FEST 2023', 'nombre' => 'BURGER FEST 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CAF AMERICA', 'nombre' => 'CHARITIES AID FOUNDATION AMERICA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CAMPAÑA EEUU', 'nombre' => 'FOUNDATION RASMUSSEN', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CAMPAÑA NAVIDAD', 'nombre' => 'CAMPAÑA NAVIDAD', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CARGILL 2023', 'nombre' => 'CARGILL 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CENA SOLIDARIA 2023', 'nombre' => 'Movamos Alimentos, llenemos Corazones', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'CENA SOLIDARIA 2024', 'nombre' => 'CENA SOLIDARIA 2024', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'DESCENTRALIZACION 25', 'nombre' => '-', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'DESTR-CERO', 'nombre' => 'DESTRUCCION CERO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'EVENTO CORPORATIVO23', 'nombre' => 'EVENTO CORPORATIVO 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'EVENTO CORPORATIVO24', 'nombre' => 'EVENTO CORPORATIVO 2024', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'EVENTO CORPORATIVO25', 'nombre' => 'EVENTO CORPORATIVO 2025', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'EVENTO MORMONES', 'nombre' => 'EVENTO MORMONES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'FLETE SOLIDARIO', 'nombre' => 'FLETE SOLIDARIO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'FUERTES HIERRO III', 'nombre' => 'FUERTES COMO EL HIERRO MORMONES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'GFN SISTEMAS 2023', 'nombre' => 'GFN SISTEMAS 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'HCERO', 'nombre' => 'HAMBRE CERO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'HEROES', 'nombre' => 'HEROES CONTRA EL HAMBRE', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'HOTELES', 'nombre' => 'RECOJO HOTELES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'MERCADOS', 'nombre' => 'MERCADOS ITINERANTES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'NAVIDAR 2023', 'nombre' => 'NAVIDAR 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'NEUTRAL', 'nombre' => 'NEUTRAL WAYS LLC', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'NUTRICION CON GLORIA', 'nombre' => 'NUTRICION CON GLORIA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'NUTRIENDO CON GLORIA', 'nombre' => 'NUTRIENDO CON GLORIA 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PAPATON 2023', 'nombre' => 'PAPATON 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PAPATON 2024', 'nombre' => 'PAPATON 2024', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PAPATON 2025', 'nombre' => 'PAPATON 2025', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'POBREZA EXTREMA', 'nombre' => 'ATENCION POBREZA EXTREMA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PROGRAMAS', 'nombre' => 'PROGRAMAS Y CAMPAÑAS ANUALES PARA EMPRESAS', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PSOL', 'nombre' => 'PRODUCTO SOLIDARIO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'PSOL PEPSICO', 'nombre' => 'PRODUCTO SOLIDARIO PEPSICO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'REPSOL 2023', 'nombre' => 'FUERTES COMO EL HIERRO 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'REPSOL II', 'nombre' => 'FUERTES COMO EL HIERRO II', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'REPSOL III', 'nombre' => 'FUERTES COMO EL HIERRO III', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'REPSOL IV', 'nombre' => 'FUERTES COMO EL HIERRO IV', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ROCKEFELER', 'nombre' => 'GFN-ROCKEFELER', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'SAVE INTERVENCION EM', 'nombre' => 'SAVE THE CHILDREN INTERVENCION EMERGENCIA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'SEGURIDAD ALIMENTARI', 'nombre' => 'SEGURIDAD ALIMENTARIA 2023', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'TELEFONICA', 'nombre' => 'FUNDACION TELEFONICA', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'TRANSP1', 'nombre' => 'TRANSPORTE HYUNDAI', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'TRANSP2', 'nombre' => 'TRANSPORTE ISUZU', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'TRANSP3', 'nombre' => 'ISUZU 7 TM', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'VCOR', 'nombre' => 'VOLUNTARIADO CORPORATIVO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'VCOR FUND ROMERO', 'nombre' => 'VOLUNTARIADO CORPORATIVO FUNDACION ROMERO', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'VOCES', 'nombre' => 'VOCES POR LA ALIMENTACION', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'YAPE', 'nombre' => 'CAPACITACION A COMEDORES', 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        DB::table('proyectos')->insert($proyectos);
        $this->command->info('Seeder de Proyectos ejecutado con la lista oficial de BAP.');
    }
}
