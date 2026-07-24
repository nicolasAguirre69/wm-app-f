<?php

use App\Http\Controllers\BarrioController;
use App\Http\Controllers\CiudadController;
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
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
