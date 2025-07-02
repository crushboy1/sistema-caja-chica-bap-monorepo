<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Area;
use App\Models\TipoDocumentoIdentidad;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Limpieza segura de la tabla
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Obtener IDs de roles usando el modelo Eloquent
        $superAdminRole = Role::where('name', 'super_admin')->first();
        $jefeAreaRole = Role::where('name', 'jefe_area')->first();
        $jefeAdministracionRole = Role::where('name', 'jefe_administracion')->first();
        $gerenteGeneralRole = Role::where('name', 'gerente_general')->first();
        $colaboradorRole = Role::where('name', 'colaborador')->first();

        // Obtener ID de tipo de documento
        $dniType = TipoDocumentoIdentidad::where('name', 'DNI')->first();

        // Obtener IDs de las áreas
        $gerenciaGeneralArea = Area::where('name', 'Gerencia General')->first();
        $administracionContabilidadArea = Area::where('name', 'Administración y Contabilidad')->first();
        $gestionProyeccionArea = Area::where('name', 'Gestión y Proyección Social')->first();
        $tiArea = Area::where('name', 'Tecnología de la Información')->first();
        $estrategiaAlianzasArea = Area::where('name', 'Estrategia y Alianzas')->first();

        // Verificar que todos los datos necesarios existen
        if (!$superAdminRole || !$jefeAreaRole || !$jefeAdministracionRole || !$gerenteGeneralRole || !$colaboradorRole || !$dniType || !$gerenciaGeneralArea || !$administracionContabilidadArea || !$gestionProyeccionArea || !$tiArea || !$estrategiaAlianzasArea) {
            $this->command->error('No se encontraron todos los roles, áreas o tipos de documento necesarios. Ejecuta los seeders correspondientes primero.');
            return;
        }

        // --- INICIO DE REFACTORIZACIÓN: Usar User::create() ---

        // 1. Crear Super Admin
        User::create([
            'numero_documento_identidad' => '12345678',
            'last_name' => 'Admin',
            'name' => 'Super',
            'cargo' => 'Administrador de Sistema',
            'email' => 'admin@bap.com',
            'telefono' => '987654321',
            'password' => Hash::make('$clave.123'),
            'role_id' => $superAdminRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $tiArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => null,
        ]);

        // 2. Crear Gerente General (Carlos Lopez)
        $gerenteCarlos = User::create([
            'numero_documento_identidad' => '55667788',
            'last_name' => 'Lopez',
            'name' => 'Carlos',
            'cargo' => 'Gerente General',
            'email' => 'carlos.lopez@bap.com',
            'telefono' => '900112233',
            'password' => Hash::make('123456'),
            'role_id' => $gerenteGeneralRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $gerenciaGeneralArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => null,
        ]);

        // 3. Crear Jefe de Administración (Maria Gomez)
        $jefeAdmMaria = User::create([
            'numero_documento_identidad' => '11223344',
            'last_name' => 'Gomez',
            'name' => 'Maria',
            'cargo' => 'Jefe de Administración',
            'email' => 'maria.gomez@bap.com',
            'telefono' => '998877665',
            'password' => Hash::make('123456'),
            'role_id' => $jefeAdministracionRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $administracionContabilidadArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $gerenteCarlos->id, // Reporta al Gerente General
        ]);

        // 4. Crear Jefe de Área (Juan Perez)
        $jefeAreaJuan = User::create([
            'numero_documento_identidad' => '87654321',
            'last_name' => 'Perez',
            'name' => 'Juan',
            'cargo' => 'Jefe de Área',
            'email' => 'juan.perez@bap.com',
            'telefono' => '912345678',
            'password' => Hash::make('123456'),
            'role_id' => $jefeAreaRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $gestionProyeccionArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $jefeAdmMaria->id, // Reporta al Jefe de Administración
        ]);

        // 5. Crear Colaborador (Ana Diaz) que reporta a Juan Perez
        User::create([
            'numero_documento_identidad' => '99887766',
            'last_name' => 'Diaz',
            'name' => 'Ana',
            'cargo' => 'Colaborador',
            'email' => 'ana.diaz@bap.com',
            'telefono' => '955443322',
            'password' => Hash::make('123456'),
            'role_id' => $colaboradorRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $gestionProyeccionArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $jefeAreaJuan->id, // Se asigna directamente el ID de Juan
        ]);

        // 6. Crear otro Jefe de Área (Roberto Garcia)
        User::create([
            'numero_documento_identidad' => '10000001',
            'last_name' => 'Garcia',
            'name' => 'Roberto',
            'cargo' => 'Jefe de Área',
            'email' => 'roberto.garcia@bap.com',
            'telefono' => '911223344',
            'password' => Hash::make('123456'),
            'role_id' => $jefeAreaRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $estrategiaAlianzasArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $gerenteCarlos->id, // Reporta al Gerente General
        ]);

        $this->command->info('Usuarios de prueba creados exitosamente.');
    }
}
