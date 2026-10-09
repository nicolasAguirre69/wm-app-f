<?php

namespace App\Models;

use App\Enums\EstadoTicket;
use App\Enums\PrioridadTicket;
use App\Models\Scopes\IspScope;
use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Ticket de soporte de un servicio (cliente). Número consecutivo por ISP.
 *
 * No se borra: todo lo que le pasa queda en ticket_eventos (trazabilidad).
 */
class Ticket extends Model
{
    use BelongsToIsp, HasHashid;

    protected $table = 'tickets';

    protected $appends = ['hashid'];

    protected $fillable = [
        'isp_id', 'numero', 'cliente_id', 'tipo_falla_id', 'prioridad', 'estado',
        'descripcion', 'fecha_visita', 'solucion', 'creado_por', 'cerrado_por', 'cerrado_at',
    ];

    protected function casts(): array
    {
        return [
            'prioridad' => PrioridadTicket::class,
            'estado' => EstadoTicket::class,
            'fecha_visita' => 'datetime',
            'cerrado_at' => 'datetime',
        ];
    }

    /**
     * Abre un ticket con el siguiente número de su ISP y registra el evento
     * "creado". Todo en una transacción (el número no se repite).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function abrir(Cliente $servicio, array $datos, ?int $userId): self
    {
        return DB::transaction(function () use ($servicio, $datos, $userId) {
            $ultimo = static::withoutGlobalScope(IspScope::class)
                ->where('isp_id', $servicio->isp_id)
                ->lockForUpdate()
                ->max('numero');

            $ticket = static::create([
                ...$datos,
                'isp_id' => $servicio->isp_id,
                'cliente_id' => $servicio->id,
                'numero' => ((int) $ultimo) + 1,
                'estado' => EstadoTicket::Abierto,
                'creado_por' => $userId,
            ]);

            $ticket->registrar('creado', $datos['descripcion'] ?? null, $userId);

            return $ticket;
        });
    }

    /**
     * Agrega un evento al historial (no se edita ni se borra).
     */
    public function registrar(string $tipo, ?string $contenido, ?int $userId): TicketEvento
    {
        return $this->eventos()->create(['tipo' => $tipo, 'contenido' => $contenido, 'user_id' => $userId]);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === EstadoTicket::Abierto;
    }

    // --- Relaciones ---

    /** Servicio del ticket (sin filtro de ISP: la FK compuesta ya lo garantiza). */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withoutGlobalScope(IspScope::class)->withTrashed();
    }

    public function tipoFalla(): BelongsTo
    {
        return $this->belongsTo(TipoFalla::class)->withoutGlobalScope(IspScope::class);
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(TicketEvento::class)->orderBy('id');
    }
}
