<?php

namespace App\Models;

use App\Observers\BarrioObserver;
use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Barrio: catálogo POR ISP. Pertenece a una ciudad y tiene muchas redes.
 * Al crearse, el BarrioObserver genera automáticamente sus 16 redes.
 */
#[ObservedBy(BarrioObserver::class)]
class Barrio extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $table = 'barrios';

    protected $fillable = [
        'isp_id',
        'ciudad_id',
        'nombre',
        'prefijo',
    ];

    /**
     * Relación: el barrio pertenece a una ciudad.
     */
    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    /**
     * Relación: el barrio tiene muchas redes.
     */
    public function redes(): HasMany
    {
        return $this->hasMany(Red::class);
    }
}
