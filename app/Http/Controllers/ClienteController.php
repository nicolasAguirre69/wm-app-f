<?php

namespace App\Http\Controllers;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Plan;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function __construct(private ClienteService $clienteService)
    {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = $this->clienteService->listar(
            $request->only('search', 'sort', 'direction', 'isp_id', 'facturable')
        );

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'filtros' => $request->only('search', 'sort', 'direction', 'isp_id', 'facturable'),
            // Solo el Super Admin recibe la lista de ISPs para filtrar.
            'isps' => $request->user()->is_super_admin
                ? \App\Models\Isp::orderBy('nombre')->get(['id', 'nombre'])
                : null,
            ...$this->catalogos(),
        ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $this->authorize('create', Cliente::class);

        $this->clienteService->crear(
            $request->validated(),
            $request->file('documento_digitalizado'),
        );

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $this->clienteService->actualizar(
            $cliente,
            $request->validated(),
            $request->file('documento_digitalizado'),
        );

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $this->clienteService->eliminar($cliente);

        return redirect()
            ->route('clientes.index')
            ->with('success', 'Cliente eliminado correctamente.');
    }

    /**
     * Marca/desmarca facturable. Acción EXCLUSIVA del Super Admin.
     */
    public function marcarFacturable(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('marcarFacturable', $cliente);

        $datos = $request->validate([
            'facturable' => ['required', 'boolean'],
            // Si NO es facturable, exigimos un motivo.
            'motivo_no_facturable' => ['nullable', 'required_if:facturable,false', 'string', 'max:1000'],
        ]);

        $this->clienteService->marcarFacturable(
            $cliente,
            $datos['facturable'],
            $datos['motivo_no_facturable'] ?? null,
        );

        return back()->with('success', 'Estado de facturación actualizado.');
    }

    /**
     * Catálogos (por ISP) + enums para los formularios.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'ciudades' => Ciudad::orderBy('nombre')->get(['id', 'nombre']),
            // Barrios con su ciudad_id: el frontend filtra según la ciudad elegida.
            'barrios' => Barrio::orderBy('nombre')->get(['id', 'nombre', 'ciudad_id']),
            'planes' => Plan::with(['tipoPlan', 'tipoServicio'])->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'nombre' => trim(
                    ($p->tipoPlan->nombre ?? '').' - '.($p->tipoServicio->nombre ?? '')
                    .($p->cantidad ? ' - '.$p->cantidad.'Mbps' : '')
                ),
            ]),
            'estados' => EstadoCliente::orderBy('nombre')->get(['id', 'nombre']),
            'tiposIdentificacion' => TipoIdentificacion::opciones(),
            'tiposContribuyente' => TipoContribuyente::opciones(),
        ];
    }
}
