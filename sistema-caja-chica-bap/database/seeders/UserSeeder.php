<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener IDs de roles
        $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
        $jefeAreaRole = DB::table('roles')->where('name', 'jefe_area')->first();
        $jefeAdministracionRole = DB::table('roles')->where('name', 'jefe_administracion')->first();
        $gerenteGeneralRole = DB::table('roles')->where('name', 'gerente_general')->first();
        $colaboradorRole = DB::table('roles')->where('name', 'colaborador')->first();


        // Obtener ID de tipo de documento (asumiendo que 'DNI' existe)
        $dniType = DB::table('tipo_documentos_identidad')->where('name', 'DNI')->first();

        // Obtener IDs de las áreas con los nuevos nombres
        $gerenciaGeneralArea = DB::table('areas')->where('name', 'Gerencia General')->first();
        $administracionContabilidadArea = DB::table('areas')->where('name', 'Administración y Contabilidad')->first();
        $estrategiaAlianzasArea = DB::table('areas')->where('name', 'Estrategia y Alianzas')->first();
        $gestionProyeccionArea = DB::table('areas')->where('name', 'Gestión y Proyección Social')->first();
        $voluntariadoArea = DB::table('areas')->where('name', 'Voluntariado')->first();
        $logisticaArea = DB::table('areas')->where('name', 'Logística')->first();
        $tiArea = DB::table('areas')->where('name', 'Tecnología de la Información')->first();
        $calidadProcesosArea = DB::table('areas')->where('name', 'Calidad y Procesos')->first();

        // Nota: 'Finanzas' ya no está en tu lista, por lo que su variable no se buscará y se mantendrá como null si se usaba.


        // Crear usuarios de prueba
        DB::table('users')->insert([
            [
                'numero_documento_identidad' => '12345678',
                'last_name' => 'Admin',
                'name' => 'Super',
                'cargo' => 'Administrador de Sistema',
                'email' => 'admin@bap.com',
                'telefono' => '987654321',
                'password' => Hash::make('$clave.123'), // Contraseña: '$clave.123'
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $superAdminRole ? $superAdminRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $tiArea ? $tiArea->id : null, // Asignado a Tecnología de la Información
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null, // El super_admin no tiene jefe de área
            ],
            [
                'numero_documento_identidad' => '87654321',
                'last_name' => 'Perez',
                'name' => 'Juan',
                'cargo' => 'Jefe de Área',
                'email' => 'juan.perez@bap.com',
                'telefono' => '912345678',
                'password' => Hash::make('123456'), // Contraseña: '123456'
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $jefeAreaRole ? $jefeAreaRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $gestionProyeccionArea ? $gestionProyeccionArea->id : null, // Asignado a Gestión y Proyección Social
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null, // Los jefes de área podrían no tener un jefe de área directo en este sistema
            ],
            [
                'numero_documento_identidad' => '11223344',
                'last_name' => 'Gomez',
                'name' => 'Maria',
                'cargo' => 'Jefe de Administración',
                'email' => 'maria.gomez@bap.com',
                'telefono' => '998877665',
                'password' => Hash::make('123456'), // Contraseña: '123456'
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $jefeAdministracionRole ? $jefeAdministracionRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $administracionContabilidadArea ? $administracionContabilidadArea->id : null, // Asignado a Administración y Contabilidad
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null, // El jefe de administración no tiene jefe de área
            ],
            [
                'numero_documento_identidad' => '55667788',
                'last_name' => 'Lopez',
                'name' => 'Carlos',
                'cargo' => 'Gerente General',
                'email' => 'carlos.lopez@bap.com',
                'telefono' => '900112233',
                'password' => Hash::make('123456'), // Contraseña: '123456'
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $gerenteGeneralRole ? $gerenteGeneralRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $gerenciaGeneralArea->id ?? null,
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null, // El gerente general no tiene jefe de área
            ],
            [
                'numero_documento_identidad' => '99887766',
                'last_name' => 'Diaz',
                'name' => 'Ana',
                'cargo' => 'Colaborador',
                'email' => 'ana.diaz@bap.com',
                'telefono' => '955443322',
                'password' => Hash::make('123456'), // Contraseña: '123456'
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $colaboradorRole ? $colaboradorRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $gestionProyeccionArea ? $gestionProyeccionArea->id : null, // Asignar a la misma área que Juan Perez
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null, // Temporalmente null, lo actualizaremos después de insertar para obtener el ID de Juan
            ],
            // --- Nuevos usuarios agregados ---
            [
                'numero_documento_identidad' => '10000001',
                'last_name' => 'Garcia',
                'name' => 'Roberto',
                'cargo' => 'Jefe de Área',
                'email' => 'roberto.garcia@bap.com',
                'telefono' => '911223344',
                'password' => Hash::make('123456'),
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $jefeAreaRole ? $jefeAreaRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $estrategiaAlianzasArea ? $estrategiaAlianzasArea->id : null, // Asignado a Estrategia y Alianzas
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null,
            ],
            [
                'numero_documento_identidad' => '20000002',
                'last_name' => 'Fernandez',
                'name' => 'Laura',
                'cargo' => 'Jefe de Administración',
                'email' => 'laura.fernandez@bap.com',
                'telefono' => '922334455',
                'password' => Hash::make('123456'),
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $jefeAdministracionRole ? $jefeAdministracionRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $administracionContabilidadArea ? $administracionContabilidadArea->id : null, // Asignado a Administración y Contabilidad
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null,
            ],
            [
                'numero_documento_identidad' => '30000003',
                'last_name' => 'Ramirez',
                'name' => 'Miguel',
                'cargo' => 'Gerente General',
                'email' => 'miguel.ramirez@bap.com',
                'telefono' => '933445566',
                'password' => Hash::make('123456'),
                'remember_token' => null,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'role_id' => $gerenteGeneralRole ? $gerenteGeneralRole->id : null,
                'tipo_documento_identidad_id' => $dniType ? $dniType->id : null,
                'area_id' => $gerenciaGeneralArea->id ?? null,
                'email_verified_at' => Carbon::now(),
                'jefe_area_id' => null,
            ],
        ]);

        // --- Actualizar el jefe_area_id para el colaborador existente ---
        // Obtener el ID del usuario 'Juan Perez' (Jefe de Área)
        $juanPerez = DB::table('users')->where('email', 'juan.perez@bap.com')->first();

        // Obtener el ID del usuario 'Ana Diaz' (Colaborador)
        $anaDiaz = DB::table('users')->where('email', 'ana.diaz@bap.com')->first();

        if ($juanPerez && $anaDiaz) {
            DB::table('users')
                ->where('id', $anaDiaz->id)
                ->update(['jefe_area_id' => $juanPerez->id, 'updated_at' => Carbon::now()]);
        }
    }
}
