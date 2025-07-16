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

        // Obtener IDs de roles
        $superAdminRole = Role::where('name', 'super_admin')->firstOrFail();
        $jefeAreaRole = Role::where('name', 'jefe_area')->firstOrFail();
        $jefeAdministracionRole = Role::where('name', 'jefe_administracion')->firstOrFail();
        $gerenteGeneralRole = Role::where('name', 'gerente_general')->firstOrFail();
        $colaboradorRole = Role::where('name', 'colaborador')->firstOrFail();

        // Obtener ID de tipo de documento
        $dniType = TipoDocumentoIdentidad::where('name', 'DNI')->firstOrFail();

        // Obtener IDs de las áreas con los nuevos nombres
        $gerenciaGeneralArea = Area::where('name', 'Gerencia General')->firstOrFail();
        $administracionArea = Area::where('name', 'Administración')->firstOrFail();
        $gestionSocialArea = Area::where('name', 'Gestión Social')->firstOrFail();
        $proyectosArea = Area::where('name', 'Proyectos')->firstOrFail();
        $tiArea = Area::where('name', 'Tecnología de la Información')->firstOrFail();
        $alianzasArea = Area::where('name', 'Alianzas y Estrategias')->firstOrFail();

        // --- Creación de Usuarios ---

        // 1. Super Admin
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
            'activo' => true, 
        ]);

        // 2. Gerente General (Carlos Lopez)
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
            'activo' => true, 
        ]);

        // 3. Jefe de Administración (Maria Gomez)
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
            'area_id' => $administracionArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $gerenteCarlos->id,
            'activo' => true, 
        ]);

        // 4. Jefe de Área de Gestión Social (Juan Perez)
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
            'area_id' => $gestionSocialArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $jefeAdmMaria->id,
            'activo' => true, 
        ]);

        // 5. Jefe de Área de Proyectos (Laura Torres)
        User::create([
            'numero_documento_identidad' => '20000001',
            'last_name' => 'Torres',
            'name' => 'Laura',
            'cargo' => 'Jefe de Proyectos',
            'email' => 'laura.torres@bap.com',
            'telefono' => '922334455',
            'password' => Hash::make('123456'),
            'role_id' => $jefeAreaRole->id,
            'tipo_documento_identidad_id' => $dniType->id,
            'area_id' => $proyectosArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $gerenteCarlos->id,
            'activo' => true, 
        ]);

        // 6. Colaborador (Ana Diaz) que reporta a Juan Perez
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
            'area_id' => $gestionSocialArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $jefeAreaJuan->id,
            'activo' => true, 
        ]);

        // 7. Jefe de Área de Alianzas (Roberto Garcia)
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
            'area_id' => $alianzasArea->id,
            'email_verified_at' => now(),
            'jefe_area_id' => $gerenteCarlos->id,
            'activo' => true, 
        ]);

        $this->command->info('Usuarios de prueba creados exitosamente.');
    }
}
