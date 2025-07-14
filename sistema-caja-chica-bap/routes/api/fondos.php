<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\FondoEfectivoController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters(['fondos-efectivo' => 'fondo']);
    
    Route::get('/fondos-activos-usuario', [FondoEfectivoController::class, 'getFondosActivosParaUsuario']);
    Route::get('fondos-efectivo/{fondo}/gastos-para-declarar', [FondoEfectivoController::class, 'getGastosParaDeclarar']);
    
    Route::prefix('fondos-efectivo/{fondo}')->group(function () {
        Route::get('/timeline', [FondoEfectivoController::class, 'getTimeline']);
        Route::get('/reposicion-summary', [FondoEfectivoController::class, 'getReposicionSummary']);
        Route::post('/reponer', [FondoEfectivoController::class, 'reponer']);
    });
});