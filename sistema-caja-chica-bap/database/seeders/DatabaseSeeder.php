<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta todos los seeders de la aplicación en un orden lógico y seguro.
     *
     * El orden es CRÍTICO para respetar las dependencias de claves foráneas.
     * Se agrupan los seeders por su dominio funcional.
     */
    public function run(): void
    {
        // =========================================================================
        // GRUPO 1: AUTENTICACIÓN Y PERMISOS
        // =========================================================================
        // Esta es la base del sistema de seguridad. Se debe ejecutar primero.
        // El orden interno también es importante: Roles -> Permisos -> Asignación.
        $this->call([
            RoleSeeder::class,           // 1. Crea los roles (super_admin, jefe_area, etc.).
            PermissionSeeder::class,       // 2. Crea todos los permisos disponibles.
            PermissionRoleSeeder::class,   // 3. Asigna los permisos a los roles.
        ]);

        // =========================================================================
        // GRUPO 2: ESTRUCTURA ORGANIZACIONAL Y CATÁLOGOS (Master Data)
        // =========================================================================
        // Estos son los catálogos y estructuras base que la aplicación utiliza.
        // Deben existir antes de crear usuarios o datos transaccionales.
        $this->call([
            AreaSeeder::class,                     // Crea las áreas de la empresa.
            TipoDocumentoIdentidadSeeder::class,   // Crea los tipos de documento (DNI, etc.).
            CuentaContableSeeder::class,           // Crea el catálogo inicial de cuentas contables.
        ]);

        // =========================================================================
        // GRUPO 3: USUARIOS
        // =========================================================================
        // Ahora que existen los roles, áreas y tipos de documento, podemos crear los usuarios.
        $this->call([
            UserSeeder::class,
        ]);

        // =========================================================================
        // GRUPO 4: DATOS TRANSACCIONALES DE PRUEBA (Opcional)
        // =========================================================================
        // Estos seeders crean datos de ejemplo que simulan el uso de la aplicación.
        // Dependen de que todos los seeders anteriores se hayan ejecutado.
        $this->call([
            SolicitudFondoSeeder::class,           // Crea solicitudes de fondo de ejemplo.
            DetalleGastoProyectadoSeeder::class,   // Añade detalles a esas solicitudes.
            HistorialEstadoSolicitudSeeder::class, // Genera el historial para las solicitudes.
            FondoEfectivoSeeder::class,            // Crea fondos de efectivo basados en las solicitudes aprobadas.
            // NOTA: No tenemos un GastoSeeder por ahora, pero si lo tuviéramos, iría aquí.
        ]);
    }
}
