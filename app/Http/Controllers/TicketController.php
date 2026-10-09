<?php

namespace App\Http\Controllers;

use App\Enums\EstadoTicket;
use App\Enums\PrioridadTicket;
use App\Models\Cliente;
use App\Models\Isp;
use App\Models\Ticket;
use App\Models\TicketEvento;
use App\Models\TipoFalla;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tickets de soporte por servicio, con historial (trazabilidad) de todo lo
 * que les pasa. Solo ISP principal y "Gestión completa" (TicketPolicy).
 */
class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Ticket::class);

        $filtros = $this->filtrosDe($request, ['search', 'estado', 'prioridad', 'tipo', 'isp_id']);
        $estado = $filtros['estado'] ?? 'abierto'; // por defecto: los abiertos

        $tickets = Ticket::query()
            ->with(['cliente.titular', 'tipoFalla:id,nombre', 'creador:id,name', 'isp:id,nombre'])
            ->when($estado !== 'todos', fn (Builder $q) => $q->where('estado', $estado))
            ->when(! empty($filtros['prioridad']), fn (Builder $q) => $q->where('prioridad', $filtros['prioridad']))
            ->when(! empty($filtros['tipo']), fn (Builder $q) => $q->where('tipo_falla_id', TipoFalla::decodeHashid($filtros['tipo'])))
            ->when(! empty($filtros['isp_id']), fn (Builder $q) => $q->where('isp_id', Isp::decodeHashid($filtros['isp_id'])))
            ->when(! empty($filtros['search']), function (Builder $q) use ($filtros) {
                $texto = trim($filtros['search']);
                $like = '%'.$texto.'%';
                $q->where(function (Builder $g) use ($texto, $like) {
                    $g->when(ctype_digit(ltrim($texto, '#')), fn ($n) => $n->orWhere('numero', (int) ltrim($texto, '#')))
                        ->orWhere('descripcion', 'like', $like)
                        // Agrupado (where(fn...)) para no romper la condición que une
                        // la subconsulta con el ticket.
                        ->orWhereHas('cliente', fn ($c) => $c->where(fn ($w) => $w
                            ->where('codigo_cliente', 'like', $like)
                            ->orWhereHas('titular', fn ($t) => $t->where(fn ($p) => $p
                                ->where('identificacion', 'like', $like)
                                ->orWhere('primer_nombre', 'like', $like)
                                ->orWhere('primer_apellido', 'like', $like)))));
                });
            })
            // Urgentes primero; dentro de cada prioridad, los más recientes.
            ->orderByRaw("CASE prioridad WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Ticket $t) => $this->resumen($t));

        $user = $request->user();

        return Inertia::render('tickets/index', [
            'tickets' => $tickets,
            'filtros' => [...$filtros, 'estado' => $estado],
            'abiertos' => Ticket::where('estado', EstadoTicket::Abierto)->count(),
            'urgentes' => Ticket::where('estado', EstadoTicket::Abierto)->where('prioridad', PrioridadTicket::Urgente)->count(),
            'tiposFalla' => TipoFalla::where('activo', true)->orderBy('nombre')->get(['id', 'isp_id', 'nombre']),
            'prioridades' => PrioridadTicket::opciones(),
            'isps' => $user->is_super_admin ? Isp::orderBy('nombre')->get(['id', 'nombre']) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Ticket::class);

        $request->validate(['servicio' => ['required', 'string', 'max:60']]);
        $servicio = $this->buscarServicio($request->input('servicio'));

        if (! $servicio) {
            throw ValidationException::withMessages(['servicio' => 'No se encontró un servicio con ese código en tu ISP.']);
        }

        if (! $servicio->isp?->tieneTickets()) {
            throw ValidationException::withMessages(['servicio' => 'La ISP de este servicio no maneja tickets de soporte.']);
        }

        $datos = $request->validate([
            'tipo_falla_id' => ['required', Rule::exists('tipos_falla', 'id')->where('isp_id', $servicio->isp_id)->where('activo', true)],
            'prioridad' => ['required', Rule::enum(PrioridadTicket::class)],
            'descripcion' => ['required', 'string', 'max:5000'],
            'fecha_visita' => ['nullable', 'date'],
        ], [
            'tipo_falla_id.required' => 'Elija el tipo de falla.',
            'tipo_falla_id.exists' => 'El tipo de falla no es válido para esta ISP.',
            'descripcion.required' => 'Describa el problema.',
        ]);

        $ticket = Ticket::abrir($servicio, $datos, $request->user()->id);

        // Se crea desde las opciones del servicio (Clientes): se vuelve ahí.
        return back()->with('success', "Ticket #{$ticket->numero} creado para el servicio {$servicio->codigo_cliente}.");
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load(['cliente.titular', 'cliente.barrio', 'cliente.plan.tipoServicio', 'cliente.plan.tipoPlan', 'cliente.estado', 'tipoFalla', 'creador:id,name', 'cerrador:id,name', 'isp:id,nombre', 'eventos.autor:id,name']);

        $c = $ticket->cliente;

        return Inertia::render('tickets/show', [
            'ticket' => [
                ...$this->resumen($ticket),
                'descripcion' => $ticket->descripcion,
                'solucion' => $ticket->solucion,
                'tipo_falla_id' => $ticket->tipo_falla_id,
                'fecha_visita_input' => $ticket->fecha_visita?->format('Y-m-d\TH:i'),
                'cerrador' => $ticket->cerrador?->name,
                'cerrado_at' => $ticket->cerrado_at?->format('Y-m-d H:i'),
                'servicio' => [
                    ...$this->resumen($ticket)['servicio'],
                    'barrio' => $c?->barrio?->nombre,
                    'plan' => trim(($c?->plan?->tipoPlan?->nombre ?? '').' - '.($c?->plan?->cantidad ? $c->plan->cantidad.' Mb ' : '').($c?->plan?->tipoServicio?->nombre ?? ''), ' -'),
                    'estado' => $c?->estado?->nombre,
                    'estado_color' => $c?->estado?->color,
                    'telefono' => trim(($c?->telefono_1 ?? '').($c?->telefono_2 ? ' / '.$c->telefono_2 : '')),
                ],
            ],
            'eventos' => $ticket->eventos->map(fn (TicketEvento $e) => [
                'id' => $e->id,
                'tipo' => $e->tipo,
                'contenido' => $e->contenido,
                'autor' => $e->autor?->name ?? 'Sistema',
                'fecha' => $e->created_at?->format('Y-m-d H:i'),
            ]),
            'tiposFalla' => TipoFalla::withoutGlobalScopes()->where('isp_id', $ticket->isp_id)
                ->where(fn ($q) => $q->where('activo', true)->orWhere('id', $ticket->tipo_falla_id))
                ->orderBy('nombre')->get(['id', 'nombre']),
            'prioridades' => PrioridadTicket::opciones(),
            'puede' => [
                'comentar' => Gate::allows('comentar', $ticket),
                'editar' => Gate::allows('update', $ticket),
                'cerrar' => Gate::allows('cerrar', $ticket),
            ],
        ]);
    }

    /**
     * Cambia prioridad, tipo de falla o fecha de visita. Cada cambio queda en
     * el historial ("Prioridad: Media → Alta").
     */
    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $this->exigirAbierto($ticket);

        $datos = $request->validate([
            'tipo_falla_id' => ['required', Rule::exists('tipos_falla', 'id')->where('isp_id', $ticket->isp_id)],
            'prioridad' => ['required', Rule::enum(PrioridadTicket::class)],
            'fecha_visita' => ['nullable', 'date'],
        ]);

        $antes = [
            'Tipo de falla' => $ticket->tipoFalla?->nombre,
            'Prioridad' => $ticket->prioridad?->label(),
            'Fecha de visita' => $ticket->fecha_visita?->format('Y-m-d H:i') ?? 'sin fecha',
        ];

        $ticket->fill($datos);

        if (! $ticket->isDirty()) {
            return back();
        }

        $ticket->save();
        $ticket->load('tipoFalla');

        $despues = [
            'Tipo de falla' => $ticket->tipoFalla?->nombre,
            'Prioridad' => $ticket->prioridad?->label(),
            'Fecha de visita' => $ticket->fecha_visita?->format('Y-m-d H:i') ?? 'sin fecha',
        ];

        $cambios = collect($antes)
            ->filter(fn ($valor, $campo) => $valor !== $despues[$campo])
            ->map(fn ($valor, $campo) => "{$campo}: {$valor} → {$despues[$campo]}")
            ->implode("\n");

        if ($cambios !== '') {
            $ticket->registrar('cambio', $cambios, $request->user()->id);
        }

        return back()->with('success', 'Ticket actualizado.');
    }

    public function comentar(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('comentar', $ticket);

        $datos = $request->validate(['contenido' => ['required', 'string', 'max:5000']]);

        $ticket->registrar('comentario', $datos['contenido'], $request->user()->id);
        $ticket->touch();

        return back()->with('success', 'Comentario agregado.');
    }

    public function cerrar(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('cerrar', $ticket);
        $this->exigirAbierto($ticket);

        $datos = $request->validate(
            ['solucion' => ['required', 'string', 'max:5000']],
            ['solucion.required' => 'Escriba la solución antes de cerrar el ticket.'],
        );

        $ticket->update([
            'estado' => EstadoTicket::Cerrado,
            'solucion' => $datos['solucion'],
            'cerrado_por' => $request->user()->id,
            'cerrado_at' => now(),
        ]);
        $ticket->registrar('cerrado', $datos['solucion'], $request->user()->id);

        return back()->with('success', "Ticket #{$ticket->numero} cerrado.");
    }

    public function reabrir(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('cerrar', $ticket);

        if ($ticket->estaAbierto()) {
            return back()->with('error', 'El ticket ya está abierto.');
        }

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'max:5000']],
            ['motivo.required' => 'Escriba por qué se reabre el ticket.'],
        );

        $ticket->update(['estado' => EstadoTicket::Abierto, 'cerrado_por' => null, 'cerrado_at' => null]);
        $ticket->registrar('reabierto', $datos['motivo'], $request->user()->id);

        return back()->with('success', "Ticket #{$ticket->numero} reabierto.");
    }

    // --- Apoyo ---

    private function exigirAbierto(Ticket $ticket): void
    {
        if (! $ticket->estaAbierto()) {
            throw ValidationException::withMessages(['ticket' => 'El ticket está cerrado. Reábralo para modificarlo.']);
        }
    }

    /**
     * Servicio por su hashid (desde Clientes) o por su código (escrito a mano).
     * La consulta normal respeta el aislamiento por ISP.
     */
    private function buscarServicio(?string $valor): ?Cliente
    {
        $valor = trim((string) $valor);

        if ($id = Cliente::decodeHashid($valor)) {
            if ($servicio = Cliente::find($id)) {
                return $servicio;
            }
        }

        return Cliente::where('codigo_cliente', $valor)
            ->when(! request()->user()->is_super_admin, fn ($q) => $q->where('isp_id', request()->user()->isp_id))
            ->orderBy('isp_id')
            ->first();
    }

    private function nombreTitular(?Cliente $c): string
    {
        $t = $c?->titular;

        return trim(implode(' ', array_filter([$t?->primer_nombre, $t?->segundo_nombre, $t?->primer_apellido, $t?->segundo_apellido])));
    }

    /**
     * Datos del ticket para las listas.
     *
     * @return array<string, mixed>
     */
    private function resumen(Ticket $t): array
    {
        return [
            'id' => $t->id,
            'hashid' => $t->hashid,
            'numero' => $t->numero,
            'estado' => $t->estado?->value,
            'estado_label' => $t->estado?->label(),
            'prioridad' => $t->prioridad?->value,
            'prioridad_label' => $t->prioridad?->label(),
            'prioridad_color' => $t->prioridad?->color(),
            'tipo' => $t->tipoFalla?->nombre,
            'isp' => $t->isp?->nombre,
            'fecha' => $t->created_at?->format('Y-m-d H:i'),
            'actualizado' => $t->updated_at?->format('Y-m-d H:i'),
            'fecha_visita' => $t->fecha_visita?->format('Y-m-d H:i'),
            'creador' => $t->creador?->name,
            'servicio' => [
                'hashid' => $t->cliente?->hashid,
                'codigo' => $t->cliente?->codigo_cliente,
                'direccion' => $t->cliente?->direccion,
                'titular' => $this->nombreTitular($t->cliente),
                'identificacion' => $t->cliente?->identificacion,
            ],
        ];
    }
}
