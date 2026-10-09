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
use App\Models\Pago;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\TipoFalla;
use App\Enums\PrioridadTicket;
use App\Models\Scopes\IspScope;
use App\Models\Titular;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Http\Response;
use Inertia\Response as InertiaResponse;

class ClienteController extends Controller
{
    public function __construct(private ClienteService $clienteService)
    {
    }

    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Cliente::class);

        // Los filtros llegan empaquetados en el parámetro opaco ?f= (base64).
        $filtros = $this->filtrosDe($request, ['search', 'sort', 'direction', 'isp_id', 'facturable', 'estado', 'puerto']);

        // Lista de titulares (personas), cada uno con sus servicios.
        ['titulares' => $titulares, 'totalServicios' => $totalServicios] = $this->clienteService->listar($filtros);

        return Inertia::render('clientes/index', [
            'titulares' => $titulares,
            'totalServicios' => $totalServicios,
            'filtros' => $filtros,
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
            // Puerto alquilado (filtro y columna): Super Admin o usuarios de la ISP principal.
            'muestraPuerto' => $request->user()->is_super_admin || (bool) $request->user()->isp?->esPrincipal(),
            // Tickets de soporte: se crean desde las opciones de cada servicio.
            'tickets' => $this->catalogosTickets($request),
            // Pagos y comprobantes: ISP del servicio que lo permiten (null: sin permiso).
            'pagos' => $request->user()->can('viewAny', Pago::class)
                ? ['isps' => $this->ispsConModulo()]
                : null,
            ...$this->catalogos(),
        ]);
    }

    /**
     * Tipos de falla y prioridades para el modal "Nuevo ticket" de cada
     * servicio. null si el usuario no puede crear tickets (ISP "Solo TV" o
     * sin permiso). Solo se envían tipos de ISP con el módulo: el botón
     * aparece en los servicios cuya ISP tenga tipos de falla.
     *
     * @return array<string, mixed>|null
     */
    private function catalogosTickets(Request $request): ?array
    {
        if ($request->user()->cannot('create', Ticket::class)) {
            return null;
        }

        $ispsConModulo = $this->ispsConModulo();

        return [
            'tiposFalla' => TipoFalla::where('activo', true)
                ->whereIn('isp_id', $ispsConModulo)
                ->orderBy('nombre')
                ->get(['id', 'isp_id', 'nombre']),
            'prioridades' => PrioridadTicket::opciones(),
        ];
    }

    /**
     * ISP con los módulos de tickets y pagos: la principal y las de "Gestión
     * completa".
     *
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function ispsConModulo()
    {
        return \App\Models\Isp::query()
            ->where(fn ($q) => $q->where('tipo', 'principal')->orWhere('categoria', 'gestion_completa'))
            ->pluck('id');
    }

    /**
     * Comentarios de un cliente (cuando llega ?comentarios_de=ID).
     * Filtra los de facturación si el usuario no tiene acceso.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function comentariosDe(Request $request)
    {
        $id = Cliente::decodeHashid($request->input('comentarios_de'));

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
                'hashid' => $c->hashid,
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
            $request->titular(),
        );

        // Al agregar un servicio a un titular se vuelve a la misma página/filtro.
        if ($request->titular()) {
            return back()->with('success', 'Servicio agregado correctamente.');
        }

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

        return back()->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $this->clienteService->eliminar($cliente);

        return back()->with('success', 'Servicio eliminado correctamente.');
    }

    /**
     * Muestra el documento digitalizado del servicio. Sale de la base (tabla
     * documentos_cliente), solo para usuarios que pueden ver ese cliente.
     */
    public function documento(Cliente $cliente): Response
    {
        $this->authorize('view', $cliente);

        $documento = $cliente->documento()->firstOrFail();

        // Nombre seguro para la cabecera (sin comillas ni saltos de línea).
        $nombre = str_replace(['"', "\r", "\n"], '', $documento->nombre_archivo);

        return response($documento->contenido, 200, [
            'Content-Type' => $documento->mime,
            'Content-Length' => (string) $documento->tamano,
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
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
            // De las ISP cliente, Web Master solo factura la TV: se excluyen sus
            // servicios sin TV (p. ej. "solo Internet" de una ISP con planes propios).
            ->where(fn ($q) => $q
                ->whereHas('isp', fn ($i) => $i->where('tipo', \App\Enums\TipoIsp::Principal->value))
                ->orWhereHas('plan.tipoServicio', fn ($t) => $t->where('nombre', 'like', '%TV%')))
            // La ciudad (código DANE) se obtiene a través del barrio.
            ->with(['isp:id,id_producto', 'barrio:id,nombre,ciudad_id', 'barrio.ciudad:id,codigo_dane'])
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
            'codigoMunicipioCliente' => $c->barrio?->ciudad?->codigo_dane ?? '',
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
     * Tipo de contribuyente con el nombre que usa el sistema de facturación
     * (Regimen Comun, Regimen Simplificado, Gran Contribuyente, Tercero Exterior).
     */
    private function mapearContribuyente(?TipoContribuyente $tipo): string
    {
        return $tipo?->label() ?? '';
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

        // Se busca la PERSONA (titular) con servicios en otra ISP.
        $existente = Titular::withoutGlobalScope(IspScope::class)
            ->where('identificacion', trim((string) $request->input('identificacion')))
            ->when($ispActual, fn ($q) => $q->where('isp_id', '!=', $ispActual))
            ->whereHas('clientes', fn ($q) => $q->withoutGlobalScope(IspScope::class))
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
     * Alterna el estado del cliente con un solo clic desde la lista:
     *   - ISP principal o con planes propios: "Activo" <-> "Corte".
     *   - ISP cliente solo TV: "Activo" <-> "Retirado" (solo existen esos dos).
     * Cualquier otro estado no se toca.
     */
    public function cambiarEstado(Cliente $cliente): RedirectResponse
    {
        $this->authorize('update', $cliente);

        // Corte si la ISP lo maneja (principal o con planes propios); si no, Retirado.
        $alterno = $cliente->isp?->estadoAlterno() ?? 'Retirado';

        $destino = match ($cliente->estado?->nombre) {
            'Activo' => $alterno,
            $alterno => 'Activo',
            default => null,
        };

        if ($destino === null) {
            return back()->with('error', "Solo se puede alternar entre Activo y {$alterno}.");
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
            // ISP principales: en las demás (ISP cliente) el plan de TV se asigna solo.
            'ispsPrincipales' => \App\Models\Isp::where('tipo', \App\Enums\TipoIsp::Principal->value)->pluck('id')->map(fn ($id) => (int) $id),
            // ISP que eligen el plan de cada cliente (principal + "Gestión completa").
            'ispsPlanesPropios' => \App\Models\Isp::where('tipo', \App\Enums\TipoIsp::Principal->value)
                ->orWhere('categoria', \App\Enums\CategoriaIsp::GestionCompleta->value)->pluck('id')->map(fn ($id) => (int) $id),
            'tiposIdentificacion' => TipoIdentificacion::opciones(),
            'tiposContribuyente' => TipoContribuyente::opciones(),
        ];
    }
}
