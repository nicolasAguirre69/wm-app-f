<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EstadoCliente: catálogo POR ISP (Activo, Suspendido, Retirado, Pendiente).
 */
class EstadoCliente extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

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

    protected $table = 'estados_cliente';

    protected $fillable = [
        'isp_id',
        'nombre',
        'color',
    ];

    /**
     * Clientes que tienen este estado.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class, 'estado_id');
    }
}
