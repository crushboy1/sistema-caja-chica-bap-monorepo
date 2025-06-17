<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *
     * Este método 'up' es el núcleo de la trazabilidad. Añade una nueva columna
     * a la tabla 'gastos' que servirá como clave foránea para vincular cada
     * gasto individual con el detalle del gasto que se proyectó originalmente
     * en la solicitud de fondos.
     */
    public function up(): void
    {
        // Se utiliza Schema::table() para modificar la tabla 'gastos' existente.
        Schema::table('gastos', function (Blueprint $table) {
            // Se añade la nueva columna para la clave foránea.
            // La columna se llamará 'detalle_gasto_proyectado_id'.
            $table->foreignId('detalle_gasto_proyectado_id')
                ->nullable() // Se define como 'nullable' para máxima flexibilidad. Aunque el flujo principal requerirá el vínculo, esto previene errores con datos antiguos o casos excepcionales futuros.
                ->after('id_fondo_efectivo') // Se posiciona la columna lógicamente después del ID del fondo.
                ->constrained('detalle_gastos_proyectados') // Se establece la restricción de clave foránea, que apunta a la tabla 'detalle_gastos_proyectados'. Laravel infiere el 'id' como clave primaria por defecto.
                ->onUpdate('cascade') // Si se actualiza el ID en la tabla padre, se actualiza aquí también.
                ->onDelete('set null'); // Si el detalle proyectado se elimina (caso muy raro), el vínculo en el gasto se establecerá a NULL en lugar de borrar el gasto, preservando así el registro histórico del gasto real.
        });
    }

    /**
     * Revierte las migraciones.
     *
     * Este método 'down' deshará los cambios, eliminando la columna y su
     * restricción de clave foránea, devolviendo la tabla 'gastos' a su estado anterior.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // Laravel es lo suficientemente inteligente como para eliminar tanto la
            // restricción de clave foránea como la columna en un solo paso.
            // El array contiene el nombre de la restricción que Laravel genera
            // automáticamente, aunque pasar solo el nombre de la columna suele ser suficiente.
            $table->dropForeign(['detalle_gasto_proyectado_id']);
            $table->dropColumn('detalle_gasto_proyectado_id');
        });
    }
};
