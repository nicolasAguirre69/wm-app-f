<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo GLOBAL de tipos de plan (Hogar, Comercial, Corporativo, Dedicado).
 *
 * No usa BelongsToIsp: es compartido por todos los ISPs y lo administra
 * únicamente el Super Admin.
 */
class TipoPlan extends Model
{
    // La tabla no sigue la convención de Laravel (sería 'tipo_plans'),
    // así que la indicamos explícitamente.
    protected $table = 'tipos_plan';

    protected $fillable = [
        'nombre',
    ];
}
