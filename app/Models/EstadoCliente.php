<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EstadoCliente: catálogo POR ISP.
 *
 * Regla de negocio: en las ISP de tipo "cliente" un cliente solo puede estar
 * Activo o Retirado (o también Corte, si la ISP tiene planes propios). La ISP
 * principal maneja además otros estados (Suspendido, moras...).
 */
class EstadoCliente extends Model
{
    use BelongsToIsp, HasFactory, HasHashid, SoftDeletes;

    protected $appends = ['hashid'];

    /**
     * Paleta de colores UNIVERSAL para los estados (misma en todas las ISPs).
     * Fuente única de verdad: la usan el frontend y la validación.
     */
    public const COLORES = [
        '#22c55e', // verde
        '#f59e0b', // ámbar
        '#ef4444', // rojo
        '#3b82f6', // azul
        '#8b5cf6', // morado
        '#6b7280', // gris
    ];

    /**
     * Únicos estados permitidos en las ISP de tipo "cliente".
     */
    public const PERMITIDOS_ISP_CLIENTE = ['Activo', 'Retirado'];

    /**
     * ISP cliente con planes propios (vende Internet, etc.): también puede
     * cortar el servicio.
     */
    public const PERMITIDOS_ISP_PLANES_PROPIOS = ['Activo', 'Corte', 'Retirado'];

    protected $table = 'estados_cliente';

    protected $fillable = [
        'isp_id',
        'nombre',
        'color',
        'en_estadisticas',
    ];

    protected function casts(): array
    {
        return [
            'en_estadisticas' => 'boolean',
        ];
    }

    /**
     * Clientes que tienen este estado.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'estado_id');
    }
}
