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

        $filtros = $this->filtrosDe($request, ['search', 'sort', 'direction']);

        $estados = $this->estadoService->listar($filtros);

        return Inertia::render('estados/index', [
            'estados' => $estados,
            'filtros' => $filtros,
        ]);
    }

    public function store(StoreEstadoClienteRequest $request): RedirectResponse
    {
        $this->authorize('create', EstadoCliente::class);

        $this->estadoService->crear($request->validated());

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado creado correctamente.');
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

        // En una ISP cliente, Activo y Retirado son obligatorios: no se borran.
        if (in_array($estado->nombre, $estado->isp?->estadosPermitidos() ?? [], true)) {
            return back()->with('error', "El estado \"{$estado->nombre}\" es obligatorio en esta ISP y no se puede eliminar.");
        }

        $this->estadoService->eliminar($estado);

        return redirect()
            ->route('estados.index')
            ->with('success', 'Estado eliminado correctamente.');
    }
}
