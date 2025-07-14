<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionRoleSeeder extends Seeder
{
    /**
     * Ejecuta los seeds para la tabla pivote permission_role.
     * Asigna los permisos refactorizados a cada rol del sistema.
     */
    public function run(): void
    {
        // Limpiar la tabla pivote antes de insertar para evitar conflictos.
        DB::table('permission_role')->delete();

        // Obtener los IDs de los roles y permisos para un mapeo eficiente.
        $roles = DB::table('roles')->get()->keyBy('name');
        $permissions = DB::table('permissions')->get()->keyBy('name');

        // Definir qué permisos tiene cada rol.
        $assignments = [
            'super_admin' => array_keys($permissions->toArray()), // El Super Admin tiene todos los permisos.

            'gerente_general' => [
                // Navegación
                'navigate.dashboard',
                'navigate.solicitudes',
                'navigate.declaraciones',
                'navigate.fondos',
                // Solicitudes
                'solicitudes.view.all',
                'solicitudes.create', // Puede crear sus propias solicitudes
                'solicitudes.approve.grte',
                'solicitudes.submit.descargo',
                // Declaraciones
                'declaraciones.view.all',
                'declaraciones.create', // Puede declarar sus propios gastos
                'declaraciones.resubmit',
            ],

            'jefe_administracion' => [
                // Navegación
                'navigate.dashboard',
                'navigate.solicitudes',
                'navigate.declaraciones',
                'navigate.fondos',
                'navigate.gestion.usuarios',
                // Administración
                'admin.users.manage',
                'admin.catalogos.manage',
                'admin.system.settings',
                // Solicitudes
                'solicitudes.view.all',
                'solicitudes.create', // Puede crear sus propias solicitudes
                'solicitudes.approve.adm',
                'solicitudes.submit.descargo',
                // Declaraciones
                'declaraciones.view.all',
                'declaraciones.create', // Puede declarar sus propios gastos
                'declaraciones.approve.adm',
                'declaraciones.resubmit',
                'declaraciones.reposition',
            ],

            'jefe_area' => [
                // Navegación
                'navigate.dashboard',
                'navigate.solicitudes',
                'navigate.declaraciones',
                'navigate.fondos',
                // Solicitudes
                'solicitudes.view.area',
                'solicitudes.create',
                'solicitudes.submit.descargo',
                // Declaraciones
                'declaraciones.view.area',
                'declaraciones.create',
                'declaraciones.approve.jefe',
                'declaraciones.resubmit',
            ],

            'colaborador' => [
                // Navegación
                'navigate.dashboard',
                'navigate.declaraciones',
                // Declaraciones
                'declaraciones.view.own',
                'declaraciones.create',
                'declaraciones.resubmit',
            ],
        ];

        // Preparar el array para la inserción masiva.
        $permissionRoleData = [];
        $now = Carbon::now();

        // Iterar sobre las asignaciones para construir los datos de inserción.
        foreach ($assignments as $roleName => $permissionNames) {
            if (isset($roles[$roleName])) {
                $roleId = $roles[$roleName]->id;
                foreach ($permissionNames as $permName) {
                    if (isset($permissions[$permName])) {
                        $permissionRoleData[] = [
                            'role_id' => $roleId,
                            'permission_id' => $permissions[$permName]->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }
        }

        // Insertar todas las asignaciones en la base de datos.
        DB::table('permission_role')->insert($permissionRoleData);
    }
}
