<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL de tipos de servicio
 * (Internet, Internet+TV, Internet+TV+Tel, Internet+Tel, TV).
 *
 * No usa BelongsToIsp: es compartido por todos los ISPs y lo administra
 * únicamente el Super Admin.
 */
class TipoServicio extends Model
{
    protected $table = 'tipos_servicio';

    protected $fillable = [
        'nombre',
    ];
}
