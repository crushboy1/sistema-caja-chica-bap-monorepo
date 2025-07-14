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
            // === PERMISOS DE NAVEGACIÓN ===
            // Controlan la visibilidad de los enlaces en el menú principal (MainLayout.vue).
            ['name' => 'navigate.dashboard', 'display_name' => 'Navegar a Dashboard', 'description' => 'Permite ver el enlace al Dashboard.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'navigate.solicitudes', 'display_name' => 'Navegar a Solicitudes', 'description' => 'Permite ver el enlace al Módulo de Solicitudes.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'navigate.declaraciones', 'display_name' => 'Navegar a Declaraciones', 'description' => 'Permite ver el enlace al Módulo de Declaraciones.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'navigate.fondos', 'display_name' => 'Navegar a Fondos', 'description' => 'Permite ver el enlace al Módulo de Fondos.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'navigate.gestion.usuarios', 'display_name' => 'Navegar a Gestión de Usuarios', 'description' => 'Permite ver el enlace a la Gestión de Usuarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === PERMISOS DE ADMINISTRACIÓN DEL SISTEMA ===
            ['name' => 'admin.users.manage', 'display_name' => 'Gestionar Usuarios y Roles', 'description' => 'Permite crear, editar y eliminar usuarios y sus roles.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'admin.catalogos.manage', 'display_name' => 'Gestionar Catálogos', 'description' => 'Permite gestionar listas maestras (proyectos, cuentas contables, gastos proyectados).', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'admin.system.settings', 'display_name' => 'Configuraciones del Sistema', 'description' => 'Permite configurar parámetros generales del sistema (ej. bloqueo de fechas).', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === PERMISOS: MÓDULO DE SOLICITUDES DE FONDOS ===
            ['name' => 'solicitudes.view.all', 'display_name' => 'Ver Todas las Solicitudes', 'description' => 'Permite ver todas las solicitudes de todos los usuarios.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitudes.view.area', 'display_name' => 'Ver Solicitudes del Área', 'description' => 'Permite ver las solicitudes de su propia área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitudes.create', 'display_name' => 'Crear Solicitud de Fondo', 'description' => 'Permite crear solicitudes de Apertura, Incremento, etc.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitudes.approve.adm', 'display_name' => 'Aprobar/Observar Solicitud (ADM)', 'description' => 'Permite al Jefe de ADM aprobar u observar solicitudes.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitudes.approve.grte', 'display_name' => 'Aprobar/Observar Solicitud (GRTE)', 'description' => 'Permite al Gerente General la aprobación final de solicitudes.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'solicitudes.submit.descargo', 'display_name' => 'Enviar Descargo de Solicitud', 'description' => 'Permite responder a una observación en una solicitud.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],

            // === PERMISOS: MÓDULO DE DECLARACIÓN DE GASTOS ===
            ['name' => 'declaraciones.view.all', 'display_name' => 'Ver Todas las Declaraciones', 'description' => 'Permite ver todos los gastos de todas las áreas.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.view.area', 'display_name' => 'Ver Declaraciones del Área', 'description' => 'Permite ver todos los gastos registrados por su área.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.view.own', 'display_name' => 'Ver Propias Declaraciones', 'description' => 'Permite ver únicamente los gastos que uno mismo ha registrado.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.create', 'display_name' => 'Crear Declaración de Gasto', 'description' => 'Permite registrar un nuevo gasto.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.approve.jefe', 'display_name' => 'Aprobar/Observar Gasto (Jefe Área)', 'description' => 'Permite aprobar u observar un gasto de un colaborador.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.approve.adm', 'display_name' => 'Contabilizar/Observar Gasto (ADM)', 'description' => 'Permite a ADM dar la validación final o devolver un gasto.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.resubmit', 'display_name' => 'Reenviar Gasto Observado', 'description' => 'Permite a un colaborador corregir y reenviar un gasto observado.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
            ['name' => 'declaraciones.reposition', 'display_name' => 'Reponer Fondos', 'description' => 'Permite a ADM ejecutar la reposición de un fondo.', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
        ];

        DB::table('permissions')->insert($permissions);
    }
}
