<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipo de falla de los tickets de soporte (Sin servicio, Lentitud...).
 * Catálogo POR ISP, editable por cada ISP con el módulo de tickets.
 */
class TipoFalla extends Model
{
    use BelongsToIsp, HasHashid;

    protected $table = 'tipos_falla';

    protected $appends = ['hashid'];

    protected $fillable = ['isp_id', 'nombre', 'activo'];

    /** Tipos con los que arranca cada ISP al tener el módulo. */
    public const POR_DEFECTO = [
        'Sin servicio', 'Lentitud', 'Intermitencia', 'Sin señal de TV',
        'Daño de equipo', 'Traslado', 'Instalación', 'Otro',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
