<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GastoController;
use App\Http\Controllers\API\DocumentoController;

/*
|--------------------------------------------------------------------------
| API Routes for Gastos & Documentos
|--------------------------------------------------------------------------
|
| Todas las rutas aquí están protegidas y requieren autenticación vía Sanctum.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // --- RUTAS DE DOCUMENTOS ---
    Route::prefix('documentos')->name('documentos.')->group(function () {
        Route::post('/generar-dj-consolidada', [DocumentoController::class, 'generarDjConsolidada'])
            ->name('generarDjConsolidada');
        Route::post('/generar-dj-nuevos', [DocumentoController::class, 'generarDjNuevos'])
            ->name('generarDjNuevos');
    });

    // --- RUTAS DE GASTOS ---

    // Endpoint para obtener la lista de gastos para las bandejas de aprobación (ya agrupados).
    Route::get('/gastos/para-aprobacion', [GastoController::class, 'getGastosParaAprobacion'])
        ->name('gastos.paraAprobacion');

    // Endpoint para consolidar gastos existentes en una nueva DJ.
    Route::post('/gastos/consolidate-dj', [GastoController::class, 'consolidateDj'])
        ->name('gastos.consolidateDj');

    // Endpoint para que un usuario obtenga solo sus gastos registrados.
    Route::get('/mis-gastos', [GastoController::class, 'misGastos'])
        ->name('gastos.misGastos');

    // --- RUTAS DE ACCIONES SOBRE GRUPOS DE DJ ---
    // Agrupa todas las acciones que se aplican a un paquete completo de DJ.
    Route::prefix('dj-groups/{djConsolidada}')->name('dj-groups.')->group(function () {

        // Acción del Jefe de Área para aprobar un grupo de DJ.
        Route::put('/approve', [GastoController::class, 'approveDjGroup'])->name('approve');

        // Acción de Administración para validar el documento de la DJ.
        Route::put('/validate-document', [GastoController::class, 'validateDjDocument'])->name('validateDocument');

        // Acción de Administración para marcar un grupo de DJ como 'Contabilizado'.
        Route::put('/finalize', [GastoController::class, 'finalizeDjGroupAsAccounted'])->name('finalize');
        //Reject grupal
        Route::put('/reject', [GastoController::class, 'rejectDjGroup'])->name('rejectGroup');
        // NOTA: La acción de observar es individual (gastos/{gasto}/observe) porque invalida el grupo.
        // La acción de rechazar también se puede manejar a nivel de grupo si se necesita, pero por ahora se mantiene individual.
    });


    // --- RUTAS DE ACCIONES SOBRE UN GASTO INDIVIDUAL ---
    Route::prefix('gastos/{gasto}')->name('gastos.')->group(function () {

        // Acción del Jefe de Área para aprobar un gasto individual.
        Route::put('/approve', [GastoController::class, 'approve'])->name('approve');

        // Acción de Administración para marcar un gasto individual como 'Contabilizado'.
        Route::put('/finalize', [GastoController::class, 'finalizeAsAccounted'])->name('finalize');

        // Acción para observar un gasto (invalida el grupo si pertenece a uno).
        Route::put('/observe', [GastoController::class, 'observe'])->name('observe');

        // Acción para rechazar un gasto de forma definitiva.
        Route::put('/reject', [GastoController::class, 'reject'])->name('reject');

        // Acción del registrador para corregir y reenviar un gasto observado.
        Route::post('/actualizar-observado', [GastoController::class, 'actualizarGastoObservado'])
            ->name('actualizarObservado')
            ->middleware(['check.permission:declaraciones.resubmit', 'check.periodo.cerrado']);
    });

    // --- RUTAS DE REPORTES (NUEVAS) ---
    // Endpoint para obtener la lista de gastos para la tabla de reportes.
    Route::get('/gastos/reportes', [GastoController::class, 'getReporteGastos'])->name('gastos.reportes');
    // Endpoint para exportar los gastos a Excel.
    Route::post('/gastos/exportar-reporte', [GastoController::class, 'exportReport'])->name('gastos.exportarReporte');


    // --- RUTAS ESTÁNDAR CRUD ---
    /**
     * 1. Se define explícitamente la ruta POST para crear gastos (la acción 'store').
     * 2. Se le aplican dos middlewares: el de permiso y el nuevo 'check.periodo.cerrado'.
     * 3. Se modifica la línea de 'apiResource' para que excluya la ruta 'store' y así evitar conflictos.
     */
    Route::post('/gastos', [GastoController::class, 'store'])
        ->name('gastos.store')
        ->middleware(['check.permission:declaraciones.create', 'check.periodo.cerrado']);
    Route::apiResource('gastos', GastoController::class)->except(['store']);
});
