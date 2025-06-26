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
| Rutas de la API del SGFE-BAP
|--------------------------------------------------------------------------
|
| Este archivo define todos los endpoints de la API. Las rutas se agrupan
| en públicas y protegidas (requieren autenticación vía Sanctum).
| La estructura sigue las convenciones RESTful y agrupa las acciones
| por módulo para mayor claridad.
|
*/

// =========================================================================
// == RUTAS PÚBLICAS (No requieren autenticación)
// =========================================================================

// --- Autenticación de Usuarios ---
Route::prefix('auth')->group(function () {
    // Endpoint para que los usuarios inicien sesión.
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    // Endpoint para registrar nuevos usuarios (si se habilita).
    Route::post('/register', [AuthController::class, 'register']);
});

// --- Verificación de Estado de la API ---
// Un endpoint simple para saber si la API está en línea.
Route::get('/health', fn() => response()->json(['status' => 'OK']));


// =========================================================================
// == RUTAS PROTEGIDAS (Requieren autenticación con Sanctum)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Gestión de Sesión y Usuario Autenticado ---
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    // Obtiene la información completa del usuario logueado, incluyendo su rol, permisos y área.
    Route::get('/user', fn(Request $request) => $request->user()->load('role.permissions', 'area'));


    // =========================================================================
    // MODULO: GESTIÓN DE SOLICITUDES DE FONDOS
    // =========================================================================

    // --- CRUD Básico para Solicitudes ---
    // Proporciona los endpoints estándar: index, store, show, update, destroy.
    Route::apiResource('solicitudes', SolicitudFondoController::class);
    // --- Acciones Específicas del Solicitante  ---
    // Endpoint para que el solicitante edite una solicitud que está PENDIENTE.
    Route::put('/solicitudes/{solicitud}/editar-pendiente', [SolicitudFondoController::class, 'editarSolicitudPendiente'])
        ->name('solicitudes.editar-pendiente');
    // Endpoint para que el solicitante edite una solicitud que fue OBSERVADA.
    Route::put('/solicitudes/{solicitud}/editar-observada', [SolicitudFondoController::class, 'editarSolicitudObservada'])
        ->name('solicitudes.editar-observada');
    // --- Acciones Específicas para Solicitudes ---
    Route::put('/solicitudes/{solicitud}/gestionar-aprobacion', [SolicitudFondoController::class, 'update'])
        ->name('solicitudes.gestionar-aprobacion');


    // =========================================================================
    // MODULO: GESTIÓN DE FONDOS DE EFECTIVO
    // =========================================================================

    // --- CRUD Básico para Fondos ---
    // Proporciona los endpoints estándar. Se renombra el parámetro a 'fondo' para el Route Model Binding.
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters([
        'fondos-efectivo' => 'fondo'
    ]);

    // --- Endpoints de Consulta para Fondos ---
    // Obtiene solo los fondos activos pertenecientes al ÁREA del usuario.
    Route::get('/fondos-activos-usuario', [FondoEfectivoController::class, 'getFondosActivosParaUsuario']);

    // --- NUEVA RUTA CRÍTICA ---
    // Obtiene los gastos proyectados que están pendientes de declarar para un fondo específico.
    // Este es el endpoint que alimentará el nuevo formulario de declaración de gastos.
    Route::get('fondos-efectivo/{fondo}/proyecciones-pendientes', [FondoEfectivoController::class, 'getProyeccionesPendientes']);

    // Agrupación de acciones específicas para un fondo.
    Route::prefix('fondos-efectivo/{fondo}')->group(function () {
        // Obtiene el historial completo (línea de tiempo) de un fondo.
        Route::get('/timeline', [FondoEfectivoController::class, 'getTimeline']);
        // Obtiene el resumen para calcular una reposición.
        Route::get('/reposicion-summary', [FondoEfectivoController::class, 'getReposicionSummary']);
        // Ejecuta la acción de reponer un fondo.
        Route::post('/reponer', [FondoEfectivoController::class, 'reponer']);
    });


    // =========================================================================
    // MODULO: DECLARACIÓN Y APROBACIÓN DE GASTOS
    // =========================================================================

    // --- CRUD Básico para Gastos ---
    Route::apiResource('gastos', GastoController::class);

    // --- Máquina de Estados y Flujo de Trabajo para Gastos ---
    // Se agrupan todas las acciones que cambian el estado de un gasto.
    Route::prefix('gastos/{gasto}')->group(function () {

        // -- Acciones del Jefe de Área --
        // Aprueba un gasto de un colaborador de su área.
        Route::post('/approve', [GastoController::class, 'approve']);
        // Rechaza de forma definitiva un gasto que estaba pendiente de su aprobación.
        Route::post('/reject-by-jefe', [GastoController::class, 'rejectByJefe']);
        // --- NUEVA RUTA CRÍTICA ---
        // Observa un gasto de un colaborador para que lo corrija (alternativa a rechazar).
        Route::post('/observe-by-jefe', [GastoController::class, 'observeByJefe']);

        // -- Acciones de Administración --
        // Contabiliza el gasto, marcándolo como final y descontando el saldo del fondo.
        Route::post('/finalize', [GastoController::class, 'finalizeAsAccounted']);
        // Observa un gasto para que sea corregido.
        Route::post('/observe', [GastoController::class, 'observe']);
        // Rechaza de forma definitiva un gasto que ya había sido aprobado por jefatura.
        Route::post('/reject-final', [GastoController::class, 'rejectFinal']);

        // -- Flujo de Corrección de Observaciones --
        // El Jefe de Área añade una directriz a un gasto observado.
        Route::post('/return-to-collaborator', [GastoController::class, 'returnToCollaborator']);
        // El colaborador corrige el gasto observado y lo reenvía al flujo.
        Route::put('/resubmit', [GastoController::class, 'resubmit']);
    });


    // =========================================================================
    // RECURSOS DE SOPORTE Y UTILIDADES
    // =========================================================================

    // --- Listas para Selectores (Dropdowns) en el Frontend ---
    // Obtiene todas las áreas.
    Route::get('/areas', [AreaController::class, 'index']);
    // Obtiene todas las cuentas contables activas.
    Route::get('/cuentas-contables', [CuentaContableController::class, 'index']);

    // --- Generación de Documentos ---
    // Endpoint para generar la Declaración Jurada en PDF.
    Route::post('/documentos/generar-dj', [DocumentoController::class, 'generarDJ']);
});
