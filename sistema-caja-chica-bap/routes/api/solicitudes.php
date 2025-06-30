<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\SolicitudFondoController;

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('solicitudes', SolicitudFondoController::class);

    Route::put('/solicitudes/{solicitud}/editar-pendiente', [SolicitudFondoController::class, 'editarSolicitudPendiente']);
    Route::put('/solicitudes/{solicitud}/editar-observada', [SolicitudFondoController::class, 'editarSolicitudObservada']);
    Route::put('/solicitudes/{solicitud}/gestionar-aprobacion', [SolicitudFondoController::class, 'update']);
});
