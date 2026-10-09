<?php

use App\Http\Controllers\BarrioController;
use App\Http\Controllers\CiudadController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComentarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EstadoClienteController;
use App\Http\Controllers\IspController;
use App\Http\Controllers\PagoController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\RedController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TipoFallaController;
use App\Http\Controllers\TitularController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    // Al entrar, va directo al login (o al dashboard si ya inició sesión).
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
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

    // Gestión de ISPs (solo Super Admin).
    Route::resource('isps', IspController::class)->except($sinFormularios);
    Route::get('isps/{isp}/logo', [IspController::class, 'logo'])->name('isps.logo');

    // Gestión de Usuarios (Super Admin + Admin de ISP).
    Route::resource('usuarios', UserController::class)
        ->parameters(['usuarios' => 'user'])
        ->except($sinFormularios);

    // Exportación de facturación (solo Super Admin) — antes del resource.
    Route::get('clientes/exportar-facturacion', [ClienteController::class, 'exportarFacturacion'])
        ->name('clientes.exportar');

    // Acción exclusiva del Super Admin: marcar facturable (antes del resource).
    Route::patch('clientes/{cliente}/facturable', [ClienteController::class, 'marcarFacturable'])
        ->name('clientes.facturable');

    // Alternar estado Activo <-> Corte desde la lista (antes del resource).
    Route::patch('clientes/{cliente}/estado', [ClienteController::class, 'cambiarEstado'])
        ->name('clientes.estado');

    // Documento digitalizado del servicio (guardado en la base; solo con permiso).
    Route::get('clientes/{cliente}/documento', [ClienteController::class, 'documento'])
        ->name('clientes.documento');

    // Detección de traslado: ¿esta identificación ya existe en otra ISP?
    Route::get('clientes/buscar-identificacion', [ClienteController::class, 'buscarPorIdentificacion'])
        ->name('clientes.buscar-identificacion');

    Route::resource('clientes', ClienteController::class)->except($sinFormularios);

    // Pagos del servicio y comprobante en PDF (ISP principal y "Gestión
    // completa"). No se borran: se anulan con motivo.
    Route::get('clientes/{cliente}/pagos', [PagoController::class, 'index'])->name('pagos.index');
    Route::post('clientes/{cliente}/pagos', [PagoController::class, 'store'])->name('pagos.store');
    Route::get('pagos/{pago}/comprobante', [PagoController::class, 'comprobante'])->name('pagos.comprobante');
    Route::post('pagos/{pago}/anular', [PagoController::class, 'anular'])->name('pagos.anular');

    // Tickets de soporte (ISP principal y "Gestión completa"). No se borran:
    // todo queda en su historial.
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::put('tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket}/comentarios', [TicketController::class, 'comentar'])->name('tickets.comentar');
    Route::post('tickets/{ticket}/cerrar', [TicketController::class, 'cerrar'])->name('tickets.cerrar');
    Route::post('tickets/{ticket}/reabrir', [TicketController::class, 'reabrir'])->name('tickets.reabrir');

    Route::resource('tipos-falla', TipoFallaController::class)
        ->parameters(['tipos-falla' => 'tipoFalla'])
        ->only(['index', 'store', 'update', 'destroy']);

    // Titular (la persona): editar sus datos personales. Aplica a todos sus servicios.
    Route::put('titulares/{titular}', [TitularController::class, 'update'])->name('titulares.update');

    // Comentarios de clientes.
    Route::post('clientes/{cliente}/comentarios', [ComentarioController::class, 'store'])->name('comentarios.store');
    Route::put('comentarios/{comentario}', [ComentarioController::class, 'update'])->name('comentarios.update');
    Route::delete('comentarios/{comentario}', [ComentarioController::class, 'destroy'])->name('comentarios.destroy');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
