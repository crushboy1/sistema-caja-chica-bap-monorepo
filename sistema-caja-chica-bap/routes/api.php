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
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran todas las rutas de la API de la aplicación.
|
*/

//==========================================================================
// RUTAS PÚBLICAS
//==========================================================================

// --- Autenticación ---
// Endpoints para que los usuarios inicien sesión y se registren.
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register']);
});

// --- Estado de la API ---
// Endpoint para verificar que la API está funcionando correctamente.
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});


//==========================================================================
// RUTAS PROTEGIDAS (Requieren autenticación vía Sanctum)
//==========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Gestión de Usuario y Sesión ---
    Route::get('/user', [AuthController::class, 'user']); // Obtiene la información del usuario autenticado.
    Route::post('/auth/logout', [AuthController::class, 'logout']); // Cierra la sesión del usuario.

    // --- RECURSOS PRINCIPALES (RESTful) ---
    // Proporcionan las operaciones CRUD estándar (index, show, store, update, destroy).

    // Gestión de todas las solicitudes (Apertura, Modificación, etc.).
    Route::apiResource('solicitudes-fondo', SolicitudFondoController::class);

    // Gestión de los fondos de caja chica.
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters([
        'fondos-efectivo' => 'id_fondo' // Asegura que el parámetro en la URL sea {id_fondo}
    ]);

    // Gestión de los gastos individuales (para el nuevo Módulo de Declaraciones).
    Route::apiResource('gastos', GastoController::class);


    // --- RUTAS ESPECÍFICAS DE RECURSOS ---
    // Endpoints para acciones que no encajan en el CRUD estándar.

    // Obtiene el historial de vida completo de un fondo específico (Apertura, Incrementos, etc.).
    Route::get('/fondos-efectivo/{id_fondo}/historial', [FondoEfectivoController::class, 'getFondoHistory']);
    

    // --- RUTAS DE UTILITARIOS Y CATÁLOGOS ---
    // Endpoints que devuelven listas de datos para selectores, etc.
    //Obtiene lista de cuentas contables.
    Route::get('/cuentas-contables', [CuentaContableController::class, 'index']);
    // Obtiene la lista de todas las áreas.
    Route::get('/areas', [AreaController::class, 'index']);
    Route::post('/documentos/generar-dj', [DocumentoController::class, 'generarDJ']);
});
