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
            $table->id(); // ID único del gasto
            $table->string('codigo_gasto')->unique(); // Un código único para el gasto, ej: GTO-00001

            // --- Relaciones (Foreign Keys) ---
            // Se relaciona con la tabla 'fondo_efectivo' ya existente.
            $table->foreignId('id_fondo_efectivo')->constrained('fondo_efectivo', 'id_fondo');
            // Se relaciona con la tabla 'users' para saber quién registró el gasto.
            $table->foreignId('id_registrador')->constrained('users', 'id');
            // Se relaciona con la tabla 'users' para saber qué Jefe de Área aprobó el gasto. Es nullable porque al crearse, aún no tiene aprobador.
            $table->foreignId('id_jefe_aprobador')->nullable()->constrained('users', 'id');
            // Se relaciona con la nueva tabla 'cuentas_contables'.
            $table->foreignId('id_cuenta_contable')->constrained('cuentas_contables');

            // --- Campos del Gasto ---
            $table->date('fecha_documento'); // Fecha del comprobante
            $table->string('tipo_documento'); // Boleta, Factura, Declaración Jurada, etc.
            $table->string('serie_documento')->nullable();
            $table->string('correlativo_documento')->nullable();
            $table->decimal('monto_total', 10, 2);
            $table->string('moneda', 3)->default('PEN');
            $table->text('glosa'); // Descripción detallada del gasto para contabilidad

            // --- Evidencia ---
            $table->string('ruta_evidencia')->nullable(); // Guarda el path al archivo del comprobante/DJ
            $table->boolean('es_declaracion_jurada')->default(false);
            
            // --- Máquina de Estados ---
            // Define el ciclo de vida del gasto según el flujo que definimos.
            $table->enum('estado', [
                'Pendiente de Aprobación Jefatura',
                'Aprobado por Jefatura',
                'Observado por Administración',
                'Devuelto para Corrección',
                'Contabilizado'
            ])->default('Pendiente de Aprobación Jefatura');

            // Campo para guardar el motivo de la observación del Jefe de Administración.
            $table->text('motivo_observacion_adm')->nullable();

            $table->timestamps(); // created_at y updated_at
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
