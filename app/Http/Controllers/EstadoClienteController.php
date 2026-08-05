<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstadoCliente\StoreEstadoClienteRequest;
use App\Http\Requests\EstadoCliente\UpdateEstadoClienteRequest;
use App\Models\EstadoCliente;
use App\Services\EstadoClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EstadoClienteController extends Controller
{
    public function __construct(private EstadoClienteService $estadoService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', EstadoCliente::class);

        $estados = $this->estadoService->listar(
            $request->only('search', 'sort', 'direction')
        );

        return Inertia::render('estados/index', [
            'estados' => $estados,
            'filtros' => $request->only('search', 'sort', 'direction'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', EstadoCliente::class);

        return Inertia::render('estados/create');
    }

    public function store(StoreEstadoClienteRequest $request): RedirectResponse
    {
        $this->authorize('create', EstadoCliente::class);

        $this->estadoService->crear($request->validated());

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado creado correctamente.');
    }

    public function edit(EstadoCliente $estado): Response
    {
        $this->authorize('update', $estado);

        return Inertia::render('estados/edit', [
            'estado' => $estado,
        ]);
    }

    public function update(UpdateEstadoClienteRequest $request, EstadoCliente $estado): RedirectResponse
    {
        $this->authorize('update', $estado);

        $this->estadoService->actualizar($estado, $request->validated());

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado actualizado correctamente.');
    }

    public function destroy(EstadoCliente $estado): RedirectResponse
    {
        $this->authorize('delete', $estado);

        $this->estadoService->eliminar($estado);

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado eliminado correctamente.');
    }
}
