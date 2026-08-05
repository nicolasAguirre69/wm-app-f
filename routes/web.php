<?php

use App\Http\Controllers\BarrioController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\EstadoClienteController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RedController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'isp.active'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    // CRUD de Ciudades (sin 'show': catálogo simple).
    Route::resource('ciudades', CiudadController::class)
        ->parameters(['ciudades' => 'ciudad'])
        ->except('show');

    // CRUD de Barrios.
    Route::resource('barrios', BarrioController::class)
        ->parameters(['barrios' => 'barrio'])
        ->except('show');

    // CRUD de Redes.
    Route::resource('redes', RedController::class)
        ->parameters(['redes' => 'red'])
        ->except('show');

    // CRUD de Planes.
    Route::resource('planes', PlanController::class)
        ->parameters(['planes' => 'plan'])
        ->except('show');

    // CRUD de Estados de Cliente.
    Route::resource('estados', EstadoClienteController::class)
        ->parameters(['estados' => 'estado'])
        ->except('show');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
