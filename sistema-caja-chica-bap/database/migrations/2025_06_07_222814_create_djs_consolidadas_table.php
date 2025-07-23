<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Esta tabla almacenará la información de los documentos de Declaración Jurada
     * que agrupan varios gastos.
     */
    public function up(): void
    {
        Schema::create('djs_consolidadas', function (Blueprint $table) {
            // ID único para cada documento de DJ consolidada.
            $table->id('id_dj_consolidada');

            // Ruta donde se almacena el archivo PDF o de imagen de la DJ.
            $table->string('ruta_documento');

            // Quién subió el documento, relacionado con la tabla de usuarios.
            $table->foreignId('id_uploader')->constrained('users', 'id');

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
