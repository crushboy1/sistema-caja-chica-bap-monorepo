<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_gasto')->unique();

            // --- Relaciones (Foreign Keys) ---
            $table->foreignId('id_fondo_efectivo')->constrained('fondo_efectivo', 'id_fondo');
            $table->foreignId('id_registrador')->constrained('users', 'id');
            $table->foreignId('id_jefe_aprobador')->nullable()->constrained('users', 'id');
            $table->foreignId('id_validador_adm')->nullable()->constrained('users', 'id');
            $table->foreignId('id_cuenta_contable')->constrained('cuentas_contables');
            $table->foreignId('id_dj_consolidada')->nullable()->constrained('djs_consolidadas', 'id_dj_consolidada')->onDelete('set null');
            // --- Información del Documento ---
            $table->date('fecha_documento');
            $table->string('tipo_documento');
            $table->string('serie_documento')->nullable();
            $table->string('correlativo_documento')->nullable();

            // --- Lógica de Montos y Moneda (Adaptado) ---
            $table->decimal('monto_total', 10, 2);
            $table->decimal('monto_proyectado_original', 10, 2)->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio_referencial', 8, 4)->nullable();
            $table->decimal('tipo_cambio', 8, 4)->nullable();
            $table->decimal('monto_final_pen', 10, 2)->nullable();
            
            // --- Descripción y Clasificación ---
            $table->foreignId('id_gasto_proyectado')->constrained('gastos_proyectados', 'id_gasto_proyectado');
            $table->text('glosa');
            $table->text('comentario')->nullable();
            

            // --- Evidencia ---
            $table->string('ruta_evidencia')->nullable();
            $table->boolean('es_declaracion_jurada')->default(false);

            // --- Máquina de Estados ---
            $table->enum('estado', [
                'Pendiente de Aprobación',        // Para gastos de Colaboradores esperando por Jefe
                'Pendiente de Validación DJ',     // se espera el documento de DJ consolidada para poder validarlos
                'Pendiente de Validación Contable', // Para gastos aprobados por Jefe o registrados por un Jefe
                'Observado',                      // Devuelto por Administración para corrección
                'Rechazado',                      // Rechazo definitivo
                'Contabilizado',                  // Aprobado final por ADM, descontado y listo para reposición
                'Repuesto'                        // El gasto ya fue incluido en una reposición
            ])->default('Pendiente de Aprobación');
        
            // --- CAMPOS PARA EL FLUJO DE OBSERVACIÓN Y CORRECCIÓN ---
            
            // Guarda el motivo de la observación que ingresa el Jefe de Administración.
            $table->text('motivo_observacion_adm')->nullable();
            
            // Guarda el ID del administrador que realizó la observación para trazabilidad.
            $table->foreignId('id_observador_adm')->nullable()->constrained('users', 'id');

            // Guarda el comentario que el usuario (colaborador/jefe) ingresa al corregir el gasto.
            $table->text('comentario_subsanacion')->nullable();

            // Guarda el motivo del rechazo final.
            $table->text('motivo_rechazo')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
