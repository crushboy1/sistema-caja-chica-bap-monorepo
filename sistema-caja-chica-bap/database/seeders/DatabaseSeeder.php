<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
// Se importa la clase App para poder verificar el entorno actual.
use Illuminate\Support\Facades\App;

class DatabaseSeeder extends Seeder
{
    /**
     * Ejecuta todos los seeders de la aplicación en un orden lógico y seguro.
     */
    public function run(): void
    {
        // =========================================================================
        // GRUPO 1: DATOS BASE (Se ejecutan en TODOS los entornos)
        // =========================================================================
        // Estos son los datos esenciales que la aplicación necesita para funcionar.
        $this->call([
            // Autenticación y Permisos
            RoleSeeder::class,
            PermissionSeeder::class,
            PermissionRoleSeeder::class,

            // Estructura Organizacional y Catálogos
            AreaSeeder::class,
            TipoDocumentoIdentidadSeeder::class,
            CuentaContableSeeder::class,
            ProyectoSeeder::class,
            TipoDocumentoComprobanteSeeder::class,
            ClasificacionBienServicioSeeder::class,
            TipoImpuestoSeeder::class,
            GastoProyectadoSeeder::class,
        ]);

        // =========================================================================
        // GRUPO 2: DATOS DE PRUEBA (Se ejecutan SOLO en desarrollo)
        // =========================================================================
        // Esta condición verifica si la variable APP_ENV en el archivo .env es 'local'.
        if (App::environment('local')) {
            $this->call([
                // Creación de Usuarios de prueba
                UserSeeder::class,

                // Creación de Datos Transaccionales de prueba
                SolicitudFondoSeeder::class,
                SolicitudGastoProyectadoSeeder::class,
                HistorialEstadoSolicitudSeeder::class,
                FondoEfectivoSeeder::class,
            ]);
        }
    }
}
