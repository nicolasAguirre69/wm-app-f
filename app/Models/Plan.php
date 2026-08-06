<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan: catálogo POR ISP. Combina un tipo de plan y un tipo de servicio
 * (ambos catálogos globales) con datos propios del ISP (Mbps, precio).
 */
class Plan extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $table = 'planes';

    protected $fillable = [
        'isp_id',
        'tipo_plan_id',
        'tipo_servicio_id',
        'cantidad',
        'valor',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'valor' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Relación: el tipo de plan (Hogar, Comercial...). Catálogo global.
     */
    public function tipoPlan(): BelongsTo
    {
        return $this->belongsTo(TipoPlan::class);
    }

    /**
     * Relación: el tipo de servicio (Internet, TV...). Catálogo global.
     */
    public function tipoServicio(): BelongsTo
    {
        return $this->belongsTo(TipoServicio::class);
    }

    /**
     * Clientes que tienen contratado este plan.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
