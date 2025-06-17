<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\SolicitudFondoController;
use App\Http\Controllers\API\GastoController;
use App\Http\Controllers\API\FondoEfectivoController;
use App\Http\Controllers\API\AreaController;
use App\Http\Controllers\API\CuentaContableController;
use App\Http\Controllers\API\DocumentoController;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
|
| Aquí se registran todas las rutas de la API de la aplicación.
| Estas rutas son 'stateless' y todas las protegidas usan autenticación
| vía Sanctum.
|
*/

// =========================================================================
// == RUTAS PÚBLICAS
// =========================================================================

// --- Autenticación ---
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register']);
});

// --- Estado de la API ---
Route::get('/health', fn() => response()->json(['status' => 'OK']));


// =========================================================================
// == RUTAS PROTEGIDAS
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Gestión de Usuario y Sesión ---
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // Obtiene la información del usuario autenticado junto con su rol, permisos y área.
    Route::get('/user', fn(Request $request) => $request->user()->load('role.permissions', 'area'));


    // -------------------------------------------------------------------------
    // -- GESTIÓN DE FONDOS Y SOLICITUDES
    // -------------------------------------------------------------------------

    // --- Recursos Principales (CRUD) ---
    Route::apiResource('solicitudes-fondo', SolicitudFondoController::class);
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters([
        'fondos-efectivo' => 'fondo' // Laravel usará la variable $fondo en el controlador
    ]);
    // Esta ruta obtiene solo los fondos activos de los que el usuario es responsable.
    // Esencial para el formulario de registro de gastos.
    Route::get('/fondos-activos-usuario', [FondoEfectivoController::class, 'getFondosActivosParaUsuario'])->name('fondos.activos-para-usuario');
    // --- Acciones y Flujos de Trabajo para Fondos ---
    Route::prefix('fondos-efectivo/{fondo}')->name('fondos.')->group(function () {
        // Obtiene el historial completo y unificado de un fondo (Aperturas, Reposiciones, etc.)
        Route::get('/timeline', [FondoEfectivoController::class, 'getTimeline'])->name('timeline');
        // Obtiene el resumen para la reposición (montos, etc.)
        Route::get('/reposicion-summary', [FondoEfectivoController::class, 'getReposicionSummary'])->name('reposicion-summary');
        // Ejecuta la reposición de un fondo
        Route::post('/reponer', [FondoEfectivoController::class, 'reponer'])->name('reponer');
    });

    // Ruta para actualizar el estado de una solicitud de fondo.
    Route::put('/solicitudes-fondos/{solicitud}/actualizar-estado', [SolicitudFondoController::class, 'actualizarEstado']);


    // -------------------------------------------------------------------------
    // -- GESTIÓN DE GASTOS Y FLUJO DE APROBACIÓN (v6)
    // -------------------------------------------------------------------------

    // --- Recurso Principal (CRUD) ---
    Route::apiResource('gastos', GastoController::class);

    // --- Máquina de Estados y Flujo de Trabajo para Gastos ---
    Route::prefix('gastos/{gasto}')->name('gastos.')->group(function () {
        // Acciones de Jefatura
        Route::post('/approve', [GastoController::class, 'approve'])->name('approve');
        Route::post('/reject-by-jefe', [GastoController::class, 'rejectByJefe'])->name('reject-by-jefe');

        // Acciones de Administración
        Route::post('/finalize', [GastoController::class, 'finalizeAsAccounted'])->name('finalize');
        Route::post('/observe', [GastoController::class, 'observe'])->name('observe');
        Route::post('/reject-final', [GastoController::class, 'rejectFinal'])->name('reject-final');

        // Flujo de Corrección
        Route::post('/return-to-collaborator', [GastoController::class, 'returnToCollaborator'])->name('return');
        Route::put('/resubmit', [GastoController::class, 'resubmit'])->name('resubmit');
    });


    // -------------------------------------------------------------------------
    // -- RECURSOS DE SOPORTE Y UTILIDADES
    // -------------------------------------------------------------------------

    // --- Listas para Selectores (Dropdowns) ---
    Route::get('/areas', [AreaController::class, 'index']);
    Route::get('/cuentas-contables', [CuentaContableController::class, 'index']);

    // --- Generación de Documentos ---
    Route::post('/documentos/generar-dj', [DocumentoController::class, 'generarDJ']);
});
