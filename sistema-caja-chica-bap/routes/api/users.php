<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

/*
|--------------------------------------------------------------------------
| Rutas del Módulo de Usuarios
|--------------------------------------------------------------------------
*/

//==========================================================================
// RUTAS PARA LISTAS DE SELECCIÓN (SELECTS/DROPDOWNS)
//==========================================================================

Route::get('/users/list-for-select', [UserController::class, 'listForSelect'])
    ->name('users.listForSelect')
    ->middleware('check.permission:navigate.dashboard');

Route::get('/users/list-for-audit', [UserController::class, 'listForAuditFilter'])
    ->name('users.listForAudit')
    ->middleware('check.permission:admin.audit.view');


//==========================================================================
// RUTAS CRUD PARA LA GESTIÓN DE USUARIOS (PANEL DE USUARIOS)
//==========================================================================
// COMENTARIO BAP: Se agrupan todas las rutas de gestión bajo un prefijo y un middleware común.
// Esto hace que el código sea más limpio y fácil de mantener.
Route::prefix('users')->name('users.')->middleware('check.permission:admin.users.manage')->group(function () {

    Route::get('/', [UserController::class, 'index'])->name('index');

    Route::post('/', [UserController::class, 'store'])->name('store');

    Route::get('/{user}', [UserController::class, 'show'])->name('show');

    Route::put('/{user}', [UserController::class, 'update'])->name('update');

    Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');

    Route::post('/{user}/activate', [UserController::class, 'activate'])->name('activate');
});
