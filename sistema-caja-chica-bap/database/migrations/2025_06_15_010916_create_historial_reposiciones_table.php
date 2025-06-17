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
        Schema::create('historial_reposiciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_fondo_efectivo')->constrained('fondo_efectivo', 'id_fondo')->cascadeOnDelete();
            $table->foreignId('id_usuario_accion')->constrained('users', 'id');
            $table->decimal('monto_repuesto', 10, 2);
            $table->decimal('saldo_anterior', 10, 2);
            $table->decimal('saldo_nuevo', 10, 2);
            $table->text('comentario')->nullable();
            $table->timestamp('fecha_reposicion');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_reposiciones');
    }
};
