<?php

namespace App\Http\Controllers;

use App\Http\Requests\Titular\UpdateTitularRequest;
use App\Models\Titular;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;

/**
 * Titular = la persona dueña de uno o varios servicios. Se lista dentro de
 * Clientes; aquí solo se editan sus datos personales.
 */
class TitularController extends Controller
{
    public function __construct(private ClienteService $clienteService)
    {
    }

    public function update(UpdateTitularRequest $request, Titular $titular): RedirectResponse
    {
        $this->authorize('update', $titular);

        $this->clienteService->actualizarTitular($titular, $request->validated());

        return back()->with('success', 'Datos del titular actualizados.');
    }
}
