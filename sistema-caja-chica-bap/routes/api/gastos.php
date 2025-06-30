<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\GastoController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('gastos', GastoController::class);

    Route::prefix('gastos/{gasto}')->group(function () {
        Route::post('/approve', [GastoController::class, 'approve']);
        Route::post('/reject-by-jefe', [GastoController::class, 'rejectByJefe']);
        Route::post('/observe-by-jefe', [GastoController::class, 'observeByJefe']);
        Route::post('/finalize', [GastoController::class, 'finalizeAsAccounted']);
        Route::post('/observe', [GastoController::class, 'observe']);
        Route::post('/reject-final', [GastoController::class, 'rejectFinal']);
        Route::post('/return-to-collaborator', [GastoController::class, 'returnToCollaborator']);
        Route::put('/resubmit', [GastoController::class, 'resubmit']);
    });
});
