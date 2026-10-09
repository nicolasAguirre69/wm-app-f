<?php

namespace App\Models;

use App\Models\Scopes\IspScope;
use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

/**
 * Cliente = SERVICIO contratado (código, dirección, barrio, plan, estado...).
 * Catálogo POR ISP.
 *
 * Desde la normalización (3FN) los datos de la PERSONA viven en `titulares`
 * y la ciudad se obtiene del barrio. Para no romper el resto de la app, el
 * modelo sigue exponiendo esos campos como si fueran propios:
 *   - $cliente->identificacion, ->primer_nombre, ->ciudad_id ... funcionan.
 *   - En el JSON que va al frontend aparecen con los mismos nombres de antes.
 * Para GUARDAR datos de la persona se usa Titular::sincronizar() (lo hace
 * ClienteService); en $fillable ya no están.
 */
class Cliente extends Model
{
    use BelongsToIsp, HasFactory, HasHashid, SoftDeletes;

    protected $table = 'clientes';

    // Expone el código ofuscado (hashid) al frontend.
    protected $appends = ['hashid'];

    // El titular se necesita casi siempre (nombre, cédula): se carga de una
    // vez para evitar consultas N+1.
    protected $with = ['titular'];

    protected $fillable = [
        'isp_id',
        'titular_id',
        'codigo_cliente',
        'barrio_id',
        'direccion',
        'plan_id',
        'estado_id',
        'fecha_instalacion',
        'dia_corte',
        'usuario_creador_id',
        'facturable',
        'motivo_no_facturable',
        // Solo ISP principal: el servicio va por un puerto alquilado a una ISP externa.
        'puerto_alquilado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_instalacion' => 'date',
            'dia_corte' => 'integer',
            'facturable' => 'boolean',
            'puerto_alquilado' => 'boolean',
        ];
    }

    /**
     * Crea un servicio a partir de datos "planos" (persona + servicio juntos,
     * como llegan del formulario o de un importador): resuelve el titular y
     * crea el cliente apuntando a él.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function crearConTitular(array $datos): self
    {
        $ispId = (int) ($datos['isp_id'] ?? Auth::user()?->isp_id);

        $titular = Titular::sincronizar($ispId, Arr::only($datos, Titular::CAMPOS));

        return static::create([
            ...Arr::except($datos, [...Titular::CAMPOS, 'ciudad_id']),
            'isp_id' => $ispId,
            'titular_id' => $titular->id,
        ]);
    }

    /**
     * Compatibilidad: Cliente::normalizarNombre() sigue existiendo.
     */
    public static function normalizarNombre(?string $valor): ?string
    {
        return Titular::normalizarNombre($valor);
    }

    /**
     * Lectura de los campos que ya no son columnas de `clientes`:
     * los de la persona salen del titular y ciudad_id sale del barrio.
     */
    public function getAttribute($key)
    {
        if (is_string($key) && ! array_key_exists($key, $this->attributes)) {
            if (in_array($key, Titular::CAMPOS, true)) {
                return $this->titular?->getAttribute($key);
            }

            if ($key === 'ciudad_id') {
                return $this->barrio?->ciudad_id;
            }
        }

        return parent::getAttribute($key);
    }

    /**
     * JSON hacia el frontend: mismos campos "planos" que antes de normalizar.
     *
     * @return array<string, mixed>
     */
    public function attributesToArray(): array
    {
        $atributos = parent::attributesToArray();

        if ($this->relationLoaded('titular') && $this->titular) {
            $persona = Arr::only($this->titular->attributesToArray(), Titular::CAMPOS);
            $atributos = [...$atributos, ...$persona];
        }

        if ($this->relationLoaded('barrio') && $this->barrio) {
            $atributos['ciudad_id'] = $this->barrio->ciudad_id;
        }

        return $atributos;
    }

    // --- Relaciones ---

    /**
     * Persona dueña del servicio. Sin el filtro por ISP: la FK compuesta
     * (isp_id, titular_id) ya garantiza que es del mismo ISP del servicio, y
     * así funciona también al consultar servicios de otras ISP (traslados).
     */
    public function titular(): BelongsTo
    {
        return $this->belongsTo(Titular::class)->withoutGlobalScope(IspScope::class);
    }

    /**
     * Ciudad a través del barrio (ya no se guarda en el cliente).
     */
    public function ciudad(): HasOneThrough
    {
        return $this->hasOneThrough(
            Ciudad::class,
            Barrio::class,
            'id',         // barrios.id
            'id',         // ciudades.id
            'barrio_id',  // clientes.barrio_id
            'ciudad_id',  // barrios.ciudad_id
        );
    }

    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoCliente::class, 'estado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }

    /**
     * Documento digitalizado del servicio (guardado en la base, tabla
     * documentos_cliente). Se descarga por /clientes/{cliente}/documento.
     */
    public function documento(): HasOne
    {
        return $this->hasOne(DocumentoCliente::class)->withoutGlobalScope(IspScope::class);
    }

    /**
     * Pagos del servicio (con su comprobante en PDF).
     */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Tickets de soporte del servicio.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * Comentarios/observaciones del cliente.
     */
    public function comentarios(): HasMany
    {
        return $this->hasMany(Comentario::class);
    }
}
