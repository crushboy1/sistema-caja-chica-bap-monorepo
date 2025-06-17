<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    /**
     * Ejecuta los seeds para los permisos del sistema.
     * Se han renombrado y añadido permisos para que coincidan con las acciones de los controladores.
     */
    public function run(): void
    {
        // Limpiar la tabla antes de insertar para evitar duplicados en re-seeding.
        DB::table('permissions')->delete();

        $permissions = [
            // === PERMISOS GENERALES Y DE USUARIOS ===
            ['name' => 'manage_users', 'display_name' => 'Gestionar Usuarios', 'description' => 'Permite crear, editar y eliminar usuarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'manage_roles', 'display_name' => 'Gestionar Roles y Permisos', 'description' => 'Permite gestionar roles y asignar permisos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'view_dashboard', 'display_name' => 'Ver Dashboard', 'description' => 'Acceso al panel principal del sistema.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === NUEVO: PERMISOS DE CONFIGURACIÓN ===
            ['name' => 'manage_accounting_codes', 'display_name' => 'Gestionar Cuentas Contables', 'description' => 'Permite crear, editar y desactivar cuentas contables (glosas).', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === PERMISOS: MÓDULO DE SOLICITUDES DE FONDOS ===
            ['name' => 'solicitud_view_all', 'display_name' => 'Ver Todas las Solicitudes', 'description' => 'Permite ver todas las solicitudes de todos los usuarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitud_view_own_area', 'display_name' => 'Ver Solicitudes del Área', 'description' => 'Permite ver las solicitudes de su propia área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitud_create', 'display_name' => 'Crear Solicitud de Fondo', 'description' => 'Permite crear solicitudes de Apertura, Incremento, etc.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitud_approve_adm', 'display_name' => 'Aprobar/Observar Solicitud (ADM)', 'description' => 'Permite al Jefe de ADM aprobar u observar solicitudes.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitud_approve_grte', 'display_name' => 'Aprobar/Observar Solicitud (GRTE)', 'description' => 'Permite al Gerente General la aprobación final de solicitudes.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitud_submit_descargo', 'display_name' => 'Enviar Descargo de Solicitud', 'description' => 'Permite responder a una observación en una solicitud.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === PERMISOS: MÓDULO DE DECLARACIÓN DE GASTOS ===
            // -- Permisos de Creación y Visualización --
            ['name' => 'gasto_view_all', 'display_name' => 'Ver Todos los Gastos', 'description' => 'Permite ver todos los gastos de todas las áreas.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_view_own_area', 'display_name' => 'Ver Gastos del Área', 'description' => 'Permite ver todos los gastos registrados por su área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_view_own', 'display_name' => 'Ver Propios Gastos', 'description' => 'Permite ver únicamente los gastos que uno mismo ha registrado.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_create', 'display_name' => 'Crear Declaración de Gasto', 'description' => 'Permite registrar un nuevo gasto.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // -- Acciones del Jefe de Área --
            ['name' => 'gasto_approve_by_jefe', 'display_name' => 'Aprobar Gasto (Jefe Área)', 'description' => 'Permite aprobar un gasto de un colaborador.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_observe_by_jefe', 'display_name' => 'Observar Gasto (Jefe Área)', 'description' => 'Permite devolver un gasto a un colaborador para su corrección.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_reject_by_jefe', 'display_name' => 'Rechazar Gasto (Jefe Área)', 'description' => 'Permite rechazar un gasto de un colaborador de forma definitiva.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // -- Acciones de Administración --
            ['name' => 'gasto_finalize_by_adm', 'display_name' => 'Contabilizar Gasto (ADM)', 'description' => 'Permite a ADM dar la validación final y descontar el fondo.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_observe_by_adm', 'display_name' => 'Observar Gasto (ADM)', 'description' => 'Permite a ADM devolver un gasto para corrección.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'gasto_reject_by_adm', 'display_name' => 'Rechazar Gasto (ADM)', 'description' => 'Permite a ADM rechazar un gasto de forma definitiva.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // -- Acciones del Colaborador --
            ['name' => 'gasto_resubmit_observed', 'display_name' => 'Reenviar Gasto Observado', 'description' => 'Permite a un colaborador corregir y reenviar un gasto observado.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // -- Permisos de Reposición --
            ['name' => 'fund_reposition', 'display_name' => 'Reponer Fondos', 'description' => 'Permite a ADM ejecutar la reposición de un fondo.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('permissions')->insert($permissions);
    }
}
