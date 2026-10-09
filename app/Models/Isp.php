<?php

namespace App\Models;

use App\Enums\CategoriaIsp;
use App\Enums\TipoIsp;
use App\Models\Scopes\IspScope;
use App\Observers\IspObserver;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[ObservedBy(IspObserver::class)]
class Isp extends Model
{
    use HasFactory, HasHashid, SoftDeletes;

    protected $appends = ['hashid'];

    /**
     * Atributos asignables masivamente.
     *
     * $fillable protege contra "mass assignment": solo estos campos pueden
     * llenarse de golpe con Isp::create($request->all()). Evita que un
     * atacante inyecte campos que no debería (ej. is_admin) vía formulario.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'activo',
        'id_producto',
        // Categoría de la ISP cliente: solo TV o gestión completa (CategoriaIsp).
        'categoria',
        // Encabezado de los comprobantes de pago.
        'nit',
        'direccion',
        'telefono',
    ];

    /**
     * Conversión automática de tipos.
     *
     * - 'tipo' se convierte al Enum TipoIsp: al leer da un objeto Enum,
     *   al guardar solo acepta valores válidos del Enum.
     * - 'activo' se maneja como booleano real (true/false), no como 1/0.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoIsp::class,
            'activo' => 'boolean',
            'categoria' => CategoriaIsp::class,
        ];
    }

    /**
     * ¿Es la ISP principal (Web Master)? Las demás son ISP "cliente".
     */
    public function esPrincipal(): bool
    {
        return $this->tipo === TipoIsp::Principal;
    }

    /**
     * ¿Administra sus planes y clientes? La principal siempre; una ISP cliente
     * solo si su categoría es "Gestión completa". Si no (Solo TV), todos sus
     * clientes llevan el plan de TV, asignado automáticamente.
     */
    public function tienePlanesPropios(): bool
    {
        return $this->esPrincipal() || $this->categoria === CategoriaIsp::GestionCompleta;
    }

    /**
     * ¿Tiene el módulo de tickets de soporte? La principal y las ISP de
     * "Gestión completa" (las mismas que administran sus planes).
     */
    public function tieneTickets(): bool
    {
        return $this->tienePlanesPropios();
    }

    /**
     * ¿Registra pagos y genera comprobantes? La principal y las ISP de
     * "Gestión completa".
     */
    public function tienePagos(): bool
    {
        return $this->tienePlanesPropios();
    }

    /**
     * Logo de la ISP (tabla aparte, para no cargar la imagen en cada consulta).
     */
    public function logo(): HasOne
    {
        return $this->hasOne(IspLogo::class);
    }

    /**
     * Estados de cliente permitidos en esta ISP:
     *   - principal: cualquiera (null);
     *   - ISP cliente "Gestión completa": Activo, Corte y Retirado;
     *   - ISP cliente "Solo TV": Activo y Retirado.
     *
     * @return list<string>|null
     */
    public function estadosPermitidos(): ?array
    {
        if ($this->esPrincipal()) {
            return null;
        }

        return $this->categoria === CategoriaIsp::GestionCompleta
            ? EstadoCliente::PERMITIDOS_ISP_PLANES_PROPIOS
            : EstadoCliente::PERMITIDOS_ISP_CLIENTE;
    }

    /**
     * Estado al que se alterna "Activo" con un clic en la lista: Corte si la
     * ISP lo maneja, si no Retirado.
     */
    public function estadoAlterno(): string
    {
        $permitidos = $this->estadosPermitidos();

        return $permitidos === null || in_array('Corte', $permitidos, true) ? 'Corte' : 'Retirado';
    }

    /**
     * Plan de TV por defecto de una ISP cliente: todos sus clientes lo llevan,
     * así que se asigna solo al crear el cliente. Si la ISP aún no lo tiene,
     * se crea (Hogar - TV, sin velocidad, valor 0).
     */
    public function planTv(): Plan
    {
        $tv = TipoServicio::where('nombre', 'TV')->firstOrFail();

        $plan = Plan::withoutGlobalScope(IspScope::class)
            ->where('isp_id', $this->id)
            ->where('tipo_servicio_id', $tv->id)
            ->orderByDesc('activo')
            ->orderBy('id')
            ->first();

        return $plan ?? Plan::withoutGlobalScope(IspScope::class)->create([
            'isp_id' => $this->id,
            'tipo_plan_id' => TipoPlan::where('nombre', 'Hogar')->value('id') ?? TipoPlan::orderBy('id')->value('id'),
            'tipo_servicio_id' => $tv->id,
            'cantidad' => null,
            'valor' => 0,
            'activo' => true,
        ]);
    }

    /**
     * Relación: los usuarios que pertenecen a este ISP.
     *
     * hasMany es la inversa de belongsTo: un ISP tiene muchos usuarios.
     * Permite escribir $isp->users para obtener la colección de usuarios.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relación: los clientes de este ISP.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * Relación: las personas (titulares) de este ISP.
     */
    public function titulares(): HasMany
    {
        return $this->hasMany(Titular::class);
    }
}
