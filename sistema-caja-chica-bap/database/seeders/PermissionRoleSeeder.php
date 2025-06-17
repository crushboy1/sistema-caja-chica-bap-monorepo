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
                'view_dashboard',
                'solicitud_view_all',
                'solicitud_approve_grte', // Aprueba/Observa solicitudes de fondo.
            ],

            'jefe_administracion' => [
                'view_dashboard',
                'manage_accounting_codes', // NUEVO: Puede gestionar el catálogo de cuentas/glosas.
                'solicitud_view_all',
                'solicitud_approve_adm', // Aprueba/Observa solicitudes hacia Gerencia.
                'gasto_view_all',
                'gasto_finalize_by_adm', // Contabiliza el gasto (acción final).
                'gasto_observe_by_adm',  // Observa cualquier gasto.
                'gasto_reject_by_adm',   // Rechaza cualquier gasto.
                'fund_reposition',       // Ejecuta la reposición.
            ],

            'jefe_area' => [
                'view_dashboard',
                'solicitud_view_own_area',
                'solicitud_create',
                'solicitud_submit_descargo',
                'gasto_view_own_area',
                'gasto_create',
                'gasto_approve_by_jefe',      // Aprueba gastos de su equipo.
                'gasto_observe_by_jefe',      // NUEVO: Puede observar gastos de su equipo.
                'gasto_reject_by_jefe',       // Rechaza gastos de su equipo.
                'gasto_resubmit_observed',    // Puede corregir sus propios gastos observados.
            ],

            'colaborador' => [
                'view_dashboard',
                'gasto_view_own',
                'gasto_create',
                'gasto_resubmit_observed', // Puede corregir sus propios gastos observados.
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
