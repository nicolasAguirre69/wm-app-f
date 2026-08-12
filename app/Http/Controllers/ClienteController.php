<?php

namespace App\Http\Controllers;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Barrio;
use App\Models\Ciudad;
use App\Models\Cliente;
use App\Models\Comentario;
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
            $request->only('search', 'sort', 'direction', 'isp_id', 'facturable', 'estado')
        );

        return Inertia::render('clientes/index', [
            'clientes' => $clientes,
            'filtros' => $request->only('search', 'sort', 'direction', 'isp_id', 'facturable', 'estado'),
            // Nombres de estado disponibles para filtrar (solo los marcados).
            'estadosFiltro' => EstadoCliente::where('en_estadisticas', true)
                ->orderBy('nombre')->pluck('nombre')->unique()->values(),
            // Solo el Super Admin recibe la lista de ISPs para filtrar.
            'isps' => $request->user()->is_super_admin
                ? \App\Models\Isp::orderBy('nombre')->get(['id', 'nombre'])
                : null,
            // Comentarios del cliente solicitado (carga bajo demanda).
            'comentarios' => $this->comentariosDe($request),
            'puedeFacturacion' => $request->user()->puedeVerFacturacion(),
            ...$this->catalogos(),
        ]);
    }

    /**
     * Comentarios de un cliente (cuando llega ?comentarios_de=ID).
     * Filtra los de facturación si el usuario no tiene acceso.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function comentariosDe(Request $request)
    {
        $id = $request->integer('comentarios_de');

        if (! $id) {
            return collect();
        }

        $cliente = Cliente::find($id); // scoped por ISP (super admin ve todos)

        if (! $cliente || $request->user()->cannot('view', $cliente)) {
            return collect();
        }

        $puedeFacturacion = $request->user()->puedeVerFacturacion();

        return $cliente->comentarios()
            ->with('autor:id,name')
            ->when(! $puedeFacturacion, fn ($q) => $q->where('tipo', 'seguimiento'))
            ->latest()
            ->get()
            ->map(fn (Comentario $c) => [
                'id' => $c->id,
                'tipo' => $c->tipo->value,
                'contenido' => $c->contenido,
                'autor' => $c->autor?->name,
                'fecha' => $c->created_at->format('Y-m-d H:i'),
                'puede_borrar' => $request->user()->is_super_admin || $c->user_id === $request->user()->id,
            ]);
    }

    public function store(StoreClienteRequest $request): RedirectResponse
    {
        $this->authorize('create', Cliente::class);

        $this->clienteService->crear(
            $request->validated(),
            $request->file('documento_digitalizado'),
            $request->boolean('trasladar'),
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
     * Exporta en .txt (JSON) los clientes facturables y activos de todas las
     * ISPs, para el sistema de facturación. Solo Super Admin.
     */
    public function exportarFacturacion(Request $request)
    {
        abort_unless($request->user()->is_super_admin, 403);

        $clientes = Cliente::query()
            ->where('facturable', true)
            ->whereHas('estado', fn ($q) => $q->where('nombre', 'Activo'))
            ->with(['isp:id,id_producto', 'barrio:id,nombre', 'ciudad:id,codigo_dane'])
            ->get();

        $data = $clientes->map(fn (Cliente $c) => [
            'clienteIdentificacion' => $c->identificacion,
            'tipoContribuyente' => $this->mapearContribuyente($c->tipo_contribuyente),
            'tipoIdentificacion' => $c->tipo_identificacion?->value,
            'clienteNombres' => trim($c->primer_nombre.' '.($c->segundo_nombre ?? '')),
            'clienteApellidos' => trim($c->primer_apellido.' '.($c->segundo_apellido ?? '')),
            'clienteTelefono' => $c->telefono_1,
            'clienteCorreo' => $c->correo ?? '',
            'clienteBarrio' => $c->barrio?->nombre ?? '',
            'clienteDireccion' => $c->direccion,
            'codigoMunicipioCliente' => $c->ciudad?->codigo_dane ?? '',
            'idProducto' => $c->isp?->id_producto,
        ])->values();

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $nombre = 'facturacion_'.now()->format('Y-m-d').'.txt';

        return response($json, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /**
     * Mapea el tipo de contribuyente al texto que espera la facturación.
     */
    private function mapearContribuyente(?\App\Enums\TipoContribuyente $tipo): string
    {
        return match ($tipo) {
            \App\Enums\TipoContribuyente::RegimenComun => 'Regimen comun',
            \App\Enums\TipoContribuyente::Natural => 'Persona natural',
            \App\Enums\TipoContribuyente::Juridica => 'Persona juridica',
            \App\Enums\TipoContribuyente::GranContribuyente => 'Gran contribuyente',
            \App\Enums\TipoContribuyente::RegimenSimple => 'Regimen simplificado',
            \App\Enums\TipoContribuyente::NoResponsableIva => 'No responsable de IVA',
            default => '',
        };
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
     * Detección de traslado: dice si esa identificación ya existe en OTRA ISP.
     * Lo consulta el formulario de alta para ofrecer trasladar en vez de duplicar.
     */
    public function buscarPorIdentificacion(Request $request): \Illuminate\Http\JsonResponse
    {
        $this->authorize('create', Cliente::class);

        $request->validate(['identificacion' => ['required', 'string', 'max:255']]);

        $ispActual = $request->user()->isp_id;

        $existente = Cliente::withoutGlobalScope(\App\Models\Scopes\IspScope::class)
            ->where('identificacion', $request->input('identificacion'))
            ->when($ispActual, fn ($q) => $q->where('isp_id', '!=', $ispActual))
            ->with('isp:id,nombre')
            ->first();

        if (! $existente) {
            return response()->json(['existe' => false]);
        }

        return response()->json([
            'existe' => true,
            'isp' => $existente->isp?->nombre,
            // Datos personales para pre-llenar el formulario (ahorra retipear).
            'cliente' => [
                'tipo_identificacion' => $existente->tipo_identificacion?->value,
                'tipo_contribuyente' => $existente->tipo_contribuyente?->value,
                'primer_nombre' => $existente->primer_nombre,
                'segundo_nombre' => $existente->segundo_nombre,
                'primer_apellido' => $existente->primer_apellido,
                'segundo_apellido' => $existente->segundo_apellido,
                'telefono_1' => $existente->telefono_1,
                'telefono_2' => $existente->telefono_2,
                'correo' => $existente->correo,
            ],
        ]);
    }

    /**
     * Alterna el estado del cliente entre "Activo" y "Corte".
     * Es la operación diaria de la oficina (cortar / reactivar el servicio)
     * con un solo clic desde la lista. Cualquier otro estado no se toca.
     */
    public function cambiarEstado(Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        $destino = match ($cliente->estado?->nombre) {
            'Activo' => 'Corte',
            'Corte' => 'Activo',
            default => null,
        };

        if ($destino === null) {
            return back()->with('error', 'Solo se puede alternar entre Activo y Corte.');
        }

        // Buscamos el estado destino DENTRO del mismo ISP del cliente.
        $estadoDestino = EstadoCliente::where('isp_id', $cliente->isp_id)
            ->where('nombre', $destino)
            ->first();

        if (! $estadoDestino) {
            return back()->with('error', "El estado \"{$destino}\" no existe en este ISP.");
        }

        $cliente->update(['estado_id' => $estadoDestino->id]);

        return back()->with('success', "Cliente cambiado a \"{$destino}\".");
    }

    /**
     * Catálogos (por ISP) + enums para los formularios.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            // Ciudades es catálogo global.
            'ciudades' => Ciudad::orderBy('nombre')->get(['id', 'nombre']),
            // Barrios/planes/estados llevan isp_id: el frontend los acota a la
            // ISP del cliente que se edita (relevante para el Super Admin).
            'barrios' => Barrio::orderBy('nombre')->get(['id', 'nombre', 'ciudad_id', 'isp_id']),
            'planes' => Plan::with(['tipoPlan', 'tipoServicio'])->get()->map(fn (Plan $p) => [
                'id' => $p->id,
                'isp_id' => $p->isp_id,
                'nombre' => trim(
                    ($p->tipoPlan->nombre ?? '').' - '.($p->tipoServicio->nombre ?? '')
                    .($p->cantidad ? ' - '.$p->cantidad.'Mbps' : '')
                ),
            ]),
            'estados' => EstadoCliente::orderBy('nombre')->get(['id', 'nombre', 'isp_id']),
            'tiposIdentificacion' => TipoIdentificacion::opciones(),
            'tiposContribuyente' => TipoContribuyente::opciones(),
        ];
    }
}
