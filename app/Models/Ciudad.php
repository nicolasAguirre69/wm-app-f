<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ciudad: catálogo POR ISP.
 *
 * Usa BelongsToIsp: todas las consultas se filtran automáticamente por el
 * ISP del usuario, y al crear se rellena el isp_id solo.
 */
class Ciudad extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    // 'Ciudad' se pluralizaría como 'ciudads' en inglés; lo corregimos.
    protected $table = 'ciudades';

    protected $fillable = [
        'isp_id',
        'nombre',
    ];

    /**
     * Relación: una ciudad tiene muchos barrios.
     * (La usaremos en el Paso 10, cuando exista la tabla barrios.)
     */
    public function barrios(): HasMany
    {
        return $this->hasMany(Barrio::class);
    }
}
