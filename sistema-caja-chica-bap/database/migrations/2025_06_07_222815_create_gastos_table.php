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

            // --- Información del Documento ---
            $table->date('fecha_documento');
            $table->string('tipo_documento');
            $table->string('serie_documento')->nullable();
            $table->string('correlativo_documento')->nullable();

            // --- Lógica de Montos y Moneda (Adaptado) ---
            $table->decimal('monto_total', 10, 2);
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio_referencial', 8, 4)->nullable();
            $table->decimal('tipo_cambio', 8, 4)->nullable();
            $table->decimal('monto_final_pen', 10, 2)->nullable();

            // --- Descripción y Clasificación ---
            $table->text('glosa');
            $table->boolean('pertenece_proyecto')->default(false);
            $table->text('comentario')->nullable();

            // --- Evidencia ---
            $table->string('ruta_evidencia')->nullable();
            $table->boolean('es_declaracion_jurada')->default(false);

            // --- Máquina de Estados ---
            $table->enum('estado', [
                'Pendiente de Aprobación',        // Para gastos de Colaboradores esperando por Jefe
                'Pendiente de Validación Contable', // Para gastos aprobados por Jefe o registrados por un Jefe
                'Observado',                      // Devuelto por Administración para corrección
                'Rechazado',                      // Rechazo definitivo
                'Contabilizado',                  // Aprobado final por ADM, descontado y listo para reposición
                'Repuesto'                        // El gasto ya fue incluido en una reposición
            ])->default('Pendiente de Aprobación');

            $table->text('motivo_observacion_adm')->nullable();
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
