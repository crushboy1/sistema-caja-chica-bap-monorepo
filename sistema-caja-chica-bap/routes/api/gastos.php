<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GastoController;
use App\Http\Controllers\API\DocumentoController;

Route::middleware('auth:sanctum')->group(function () {

    // --- RUTAS ESTÁNDAR CRUD ---
    // Mantiene las rutas básicas: index, store, show, update, destroy.
    // 'update' (PUT /gastos/{gasto}) se puede usar para ediciones generales si es necesario en el futuro.
    Route::apiResource('gastos', GastoController::class);

    // --- RUTAS DE ACCIONES ESPECÍFICAS (FLUJO DE APROBACIÓN) ---
    // Se agrupan todas las acciones específicas para un gasto.
    Route::prefix('gastos/{gasto}')->group(function () {

        // Acción del Jefe de Área para aprobar un gasto de un colaborador.
        // Endpoint: PUT /api/gastos/{gasto}/approve
        Route::put('/approve', [GastoController::class, 'approve'])->name('gastos.approve');

        // Acción de Administración para validar y contabilizar un gasto.
        // Endpoint: PUT /api/gastos/{gasto}/finalize
        Route::put('/finalize', [GastoController::class, 'finalizeAsAccounted'])->name('gastos.finalize');

        // Acción UNIFICADA para observar un gasto (usada por Jefe de Área y Administración).
        // Endpoint: PUT /api/gastos/{gasto}/observe
        Route::put('/observe', [GastoController::class, 'observe'])->name('gastos.observe');

        // Acción UNIFICADA para rechazar un gasto (usada por Jefe de Área y Administración).
        // Endpoint: PUT /api/gastos/{gasto}/reject
        Route::put('/reject', [GastoController::class, 'reject'])->name('gastos.reject'); // Necesitarás crear este método unificado en el controller.

        // NUEVA RUTA: Acción del registrador para corregir y reenviar un gasto observado.
        // Se usa POST para manejar correctamente la subida de archivos (multipart/form-data).
        // Endpoint: POST /api/gastos/{gasto}/actualizar-observado
        Route::post('/actualizar-observado', [GastoController::class, 'actualizarGastoObservado'])->name('gastos.actualizarObservado');
    });

    // --- RUTAS ADICIONALES ---
    // Endpoint para que un usuario obtenga solo sus gastos registrados.
    Route::get('/mis-gastos', [GastoController::class, 'misGastos'])->name('gastos.misGastos');


    Route::prefix('documentos')->name('documentos.')->group(function () {

        // Endpoint para generar la Declaración Jurada consolidada.
        // Se llamará desde el componente de Vue.
        Route::post('/generar-dj-consolidada', [DocumentoController::class, 'generarDjConsolidada'])
            ->name('generarDjConsolidada');
        Route::put('/djs-consolidadas/{dj}/validar', [DocumentoController::class, 'validarDjConsolidada'])->name('djs.validar');
    });
});
