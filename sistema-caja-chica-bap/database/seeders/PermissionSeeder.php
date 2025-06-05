<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define los permisos a insertar
        $permissions = [
            // Permisos generales del sistema
            ['name' => 'manage_users', 'display_name' => 'Gestionar Usuarios', 'description' => 'Permite crear, editar y eliminar usuarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_roles', 'display_name' => 'Gestionar Roles', 'description' => 'Permite crear, editar y eliminar roles.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_permissions', 'display_name' => 'Gestionar Permisos', 'description' => 'Permite asignar y revocar permisos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view_dashboard', 'display_name' => 'Ver Dashboard', 'description' => 'Acceso al panel principal del sistema.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Permisos relacionados con Solicitudes de Fondo (Apertura, Incremento, Decremento, Cierre)
            ['name' => 'create_solicitud_fondo', 'display_name' => 'Crear Solicitud de Fondo', 'description' => 'Permite al Jefe de Área crear solicitudes de Apertura, Incremento, Decremento o Cierre de fondos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view_solicitudes', 'display_name' => 'Ver Solicitudes de Fondo', 'description' => 'Permite ver el seguimiento y detalles de las solicitudes de fondo.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Acciones del Jefe de Administración sobre Solicitudes
            ['name' => 'review_initial_solicitud_adm', 'display_name' => 'Revisar Solicitud Creada (ADM)', 'description' => 'Permite al Jefe de Administración revisar y tomar la primera acción sobre solicitudes en estado "Creada".', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'approve_solicitud_adm', 'display_name' => 'Aprobar Solicitud ADM', 'description' => 'Permite al Jefe de Administración aprobar una solicitud y enviarla a Gerencia.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'observe_solicitud_adm', 'display_name' => 'Observar Solicitud ADM', 'description' => 'Permite al Jefe de Administración observar una solicitud, requiriendo un descargo del solicitante.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'reject_final_solicitud_adm', 'display_name' => 'Rechazar Solicitud ADM (Final)', 'description' => 'Permite al Jefe de Administración rechazar definitivamente una solicitud de modificación (sin opción a descargo).', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Acciones del Gerente General sobre Solicitudes
            ['name' => 'approve_solicitud_grte', 'display_name' => 'Aprobar Solicitud GRTE', 'description' => 'Permite al Gerente General aprobar una solicitud de fondo, finalizando el proceso de aprobación.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'observe_solicitud_grte', 'display_name' => 'Observar Solicitud GRTE', 'description' => 'Permite al Gerente General observar una solicitud, requiriendo un descargo del solicitante.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'reject_final_solicitud_grte', 'display_name' => 'Rechazar Solicitud GRTE (Final)', 'description' => 'Permite al Gerente General rechazar definitivamente una solicitud de modificación (sin opción a descargo).', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Acciones del Solicitante sobre Solicitudes
            ['name' => 'submit_descargo_solicitud', 'display_name' => 'Enviar Descargo de Solicitud', 'description' => 'Permite al Jefe de Área/Colaborador enviar un descargo a una solicitud observada.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Permisos relacionados con la Liquidación de Gastos (básicos para roles)
            ['name' => 'create_expense_declaration', 'display_name' => 'Crear Declaración de Gastos', 'description' => 'Permite al Colaborador/Jefe de Área crear declaraciones de gastos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'validate_collaborator_expense', 'display_name' => 'Validar Gasto de Colaborador', 'description' => 'Permite al Jefe de Área validar gastos de su personal.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'review_expense_liquidation', 'display_name' => 'Revisar Liquidación de Gastos', 'description' => 'Permite al Jefe de Administración revisar liquidaciones de Jefes de Área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'approve_expense_liquidation', 'display_name' => 'Aprobar Liquidación de Gastos', 'description' => 'Permite al Jefe de Administración aprobar liquidaciones de Jefes de Área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'notify_liquidation_observations', 'display_name' => 'Notificar Observaciones de Liquidación', 'description' => 'Permite al Jefe de Administración notificar observaciones en liquidaciones.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'resolve_liquidation_observations', 'display_name' => 'Resolver Observaciones de Liquidación', 'description' => 'Permite al Jefe de Área/Colaborador levantar observaciones en liquidaciones.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'apply_sanction', 'display_name' => 'Aplicar Sanción', 'description' => 'Permite al Jefe de Administración aplicar sanciones por liquidaciones.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declare_expenses_in_system', 'display_name' => 'Declarar Gastos en Sistema (SAP)', 'description' => 'Permite al Jefe de Administración declarar gastos en SAP.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // Permisos relacionados con Control y Supervisión de Fondos Activos
            ['name' => 'supervise_fund', 'display_name' => 'Supervisar Fondos de Efectivo', 'description' => 'Permite al Jefe de Administración y Gerente General supervisar el estado y movimientos de los fondos activos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        // Inserta los datos en la tabla 'permissions'
        DB::table('permissions')->insert($permissions);
    }
}
