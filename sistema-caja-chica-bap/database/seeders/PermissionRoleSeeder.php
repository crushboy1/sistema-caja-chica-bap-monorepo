<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon; // Importar Carbon para los timestamps

class PermissionRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Obtener los IDs de los roles
        $superAdminRole = DB::table('roles')->where('name', 'super_admin')->first();
        $gerenteGeneralRole = DB::table('roles')->where('name', 'gerente_general')->first();
        $jefeAdministracionRole = DB::table('roles')->where('name', 'jefe_administracion')->first();
        $jefeAreaRole = DB::table('roles')->where('name', 'jefe_area')->first();
        $colaboradorRole = DB::table('roles')->where('name', 'colaborador')->first();

        // Obtener los IDs de los permisos y indexarlos por 'name' para fácil acceso
        $permissions = DB::table('permissions')->get()->keyBy('name');

        // Array para almacenar las asociaciones de permisos a roles
        $rolePermissions = [];

        // Asignar permisos al rol 'super_admin' (todos los permisos)
        if ($superAdminRole) {
            foreach ($permissions as $permission) {
                $rolePermissions[] = [
                    'role_id' => $superAdminRole->id,
                    'permission_id' => $permission->id,
                    'created_at' => Carbon::now(), // Añadir timestamps
                    'updated_at' => Carbon::now(), // Añadir timestamps
                ];
            }
        }

        // Asignar permisos al rol 'gerente_general'
        if ($gerenteGeneralRole) {
            $gerenteGeneralPermissions = [
                'view_dashboard',
                'view_solicitudes', // Puede ver el seguimiento de solicitudes
                'approve_solicitud_grte', // Aprobar solicitudes de fondo
                'observe_solicitud_grte', // Observar solicitudes de fondo
                'reject_final_solicitud_grte', // Rechazar definitivamente solicitudes de modificación
                'supervise_fund', // Supervisar fondos de efectivo
            ];
            foreach ($gerenteGeneralPermissions as $permName) {
                if (isset($permissions[$permName])) {
                    $rolePermissions[] = [
                        'role_id' => $gerenteGeneralRole->id,
                        'permission_id' => $permissions[$permName]->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
        }

        // Asignar permisos al rol 'jefe_administracion'
        if ($jefeAdministracionRole) {
            $jefeAdministracionPermissions = [
                'view_dashboard',
                'view_solicitudes', // Puede ver el seguimiento de solicitudes
                'review_initial_solicitud_adm', //revision inicial de la solicitud
                'approve_solicitud_adm', // Aprobar solicitudes a Gerencia
                'observe_solicitud_adm', // Observar solicitudes (requiere descargo)
                'reject_final_solicitud_adm', // Rechazar definitivamente solicitudes de modificación
                'review_expense_liquidation', // Revisar liquidaciones de Jefes de Área
                'approve_expense_liquidation', // Aprobar liquidaciones de Jefes de Área
                'notify_liquidation_observations', // Notificar observaciones en liquidaciones
                'apply_sanction', // Aplicar sanciones
                'declare_expenses_in_system', // Declarar gastos en SAP
                'supervise_fund', // Supervisar fondos de efectivo
            ];
            foreach ($jefeAdministracionPermissions as $permName) {
                if (isset($permissions[$permName])) {
                    $rolePermissions[] = [
                        'role_id' => $jefeAdministracionRole->id,
                        'permission_id' => $permissions[$permName]->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
        }

        // Asignar permisos al rol 'jefe_area'
        if ($jefeAreaRole) {
            $jefeAreaPermissions = [
                'view_dashboard',
                'view_solicitudes', // Puede ver el seguimiento de sus propias solicitudes
                'create_solicitud_fondo', // Crear Apertura, Incremento, Decremento, Cierre
                'submit_descargo_solicitud', // Enviar descargo a solicitudes observadas
                'create_expense_declaration', // Crear sus propias declaraciones de gastos
                'validate_collaborator_expense', // Validar gastos de sus colaboradores
                'resolve_liquidation_observations', // Resolver observaciones de sus liquidaciones
            ];
            foreach ($jefeAreaPermissions as $permName) {
                if (isset($permissions[$permName])) {
                    $rolePermissions[] = [
                        'role_id' => $jefeAreaRole->id,
                        'permission_id' => $permissions[$permName]->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
        }

        // Asignar permisos al rol 'colaborador'
        if ($colaboradorRole) {
            $colaboradorPermissions = [
                'view_dashboard',
                // 'view_solicitudes', // Si los colaboradores pueden ver el seguimiento de sus propias solicitudes (no de fondos, sino de gastos)
                'create_expense_declaration', // Crear sus propias declaraciones de gastos
                'resolve_liquidation_observations', // Resolver observaciones de sus propias declaraciones
            ];
            foreach ($colaboradorPermissions as $permName) {
                if (isset($permissions[$permName])) {
                    $rolePermissions[] = [
                        'role_id' => $colaboradorRole->id,
                        'permission_id' => $permissions[$permName]->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ];
                }
            }
        }

        // Inserta las asociaciones en la tabla pivote 'permission_role'
        // Usar insertOrIgnore para evitar duplicados si se ejecuta múltiples veces sin fresh
        DB::table('permission_role')->insertOrIgnore($rolePermissions);
    }
}
