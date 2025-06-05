<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define los roles a insertar
        // Define los roles a insertar
        $roles = [
            ['name' => 'super_admin', 'display_name' => 'Administrador del Sistema', 'description' => 'Acceso completo al sistema y gestión de usuarios/roles.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gerente_general', 'display_name' => 'Gerente General', 'description' => 'Aprueba fondos de efectivo y variaciones.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'jefe_administracion', 'display_name' => 'Jefe de Administración', 'description' => 'Revisa, evalúa, supervisa y declara gastos en SAP.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'jefe_area', 'display_name' => 'Jefe de Área', 'description' => 'Solicita y liquida fondos de efectivo, y valida gastos de colaboradores.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'colaborador', 'display_name' => 'Colaborador', 'description' => 'Declara gastos y realiza acciones básicas.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()], 
        ];

        // Inserta los datos en la tabla 'roles'
        DB::table('roles')->insert($roles);
    }
}
