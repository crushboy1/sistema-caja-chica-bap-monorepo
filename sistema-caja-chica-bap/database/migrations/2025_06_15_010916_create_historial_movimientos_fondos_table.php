<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        // Se renombra la tabla para reflejar su nuevo propósito.
        Schema::create('historial_movimientos_fondos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_fondo_efectivo')->constrained('fondo_efectivo', 'id_fondo');
            $table->foreignId('id_usuario_accion')->constrained('users', 'id');
            $table->enum('tipo_movimiento', ['Reposicion por Excedente', 'Devolucion por Sobrante','Restauracion Mensual', 'Ajuste Manual']);
            $table->decimal('monto_movimiento', 10, 2);
            $table->decimal('saldo_anterior', 10, 2);
            $table->decimal('saldo_nuevo', 10, 2);
            $table->text('comentario')->nullable();
            $table->string('ruta_comprobante')->nullable();
            $table->timestamp('fecha_movimiento')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_movimientos_fondos');
    }
};
