<?php

use App\Http\Controllers\BarrioController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadoClienteController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RedController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'isp.active'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Los formularios de crear/editar son modales en el listado, así que no
    // necesitamos rutas create/edit (páginas separadas). Solo index + acciones.
    $sinFormularios = ['create', 'edit', 'show'];

    Route::resource('ciudades', CiudadController::class)
        ->parameters(['ciudades' => 'ciudad'])
        ->except($sinFormularios);

    Route::resource('barrios', BarrioController::class)
        ->parameters(['barrios' => 'barrio'])
        ->except($sinFormularios);

    Route::resource('redes', RedController::class)
        ->parameters(['redes' => 'red'])
        ->except($sinFormularios);

    Route::resource('planes', PlanController::class)
        ->parameters(['planes' => 'plan'])
        ->except($sinFormularios);

    Route::resource('estados', EstadoClienteController::class)
        ->parameters(['estados' => 'estado'])
        ->except($sinFormularios);

    // Acción exclusiva del Super Admin: marcar facturable (antes del resource).
    Route::patch('clientes/{cliente}/facturable', [ClienteController::class, 'marcarFacturable'])
        ->name('clientes.facturable');

    Route::resource('clientes', ClienteController::class)->except($sinFormularios);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
