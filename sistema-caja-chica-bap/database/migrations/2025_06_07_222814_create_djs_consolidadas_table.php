<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacenará las Declaraciones Juradas que agrupan varios gastos.
     */
    public function up(): void
    {
        Schema::create('djs_consolidadas', function (Blueprint $table) {
            $table->id('id_dj_consolidada');
            $table->string('codigo_dj')->unique()->nullable();
            $table->foreignId('fondo_efectivo_id')->constrained('fondo_efectivo', 'id_fondo');
            $table->date('fecha_declaracion');
            $table->decimal('monto_total_declarado', 10, 2);
            $table->string('estado', 50)->default('Declarado');
            $table->foreignId('creado_por')->constrained('users', 'id');
            $table->string('ruta_documento_firmado')->nullable();
            $table->foreignId('id_uploader_firmado')->nullable()->constrained('users', 'id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('djs_consolidadas');
    }
};
