<?php

namespace App\Traits;

use App\Models\Isp;
use App\Models\Scopes\IspScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait para modelos "tenant" (que pertenecen a un ISP).
 *
 * Aplícalo con `use BelongsToIsp;` en Cliente, Plan, Barrio, Ciudad,
 * EstadoCliente, etc. Aporta tres cosas:
 *   1. El Global Scope que filtra por isp_id en cada consulta.
 *   2. Relleno automático de isp_id al crear un registro.
 *   3. La relación isp() hacia el modelo Isp.
 */
trait BelongsToIsp
{
    /**
     * Laravel llama automáticamente a este método al arrancar el modelo,
     * por la convención de nombre boot{NombreDelTrait}.
     */
    protected static function bootBelongsToIsp(): void
    {
        // 1. Registra el Global Scope: filtra todas las consultas por ISP.
        static::addGlobalScope(new IspScope);

        // 2. Antes de CREAR un registro, si no trae isp_id y hay un usuario
        //    normal autenticado, lo rellenamos con su ISP automáticamente.
        //    Así es imposible olvidar asignar el isp_id al crear.
        static::creating(function ($model) {
            if (empty($model->isp_id) && Auth::check() && ! Auth::user()->is_super_admin) {
                $model->isp_id = Auth::user()->isp_id;
            }
        });
    }

    /**
     * Relación: el ISP al que pertenece este registro.
     */
    public function isp(): BelongsTo
    {
        return $this->belongsTo(Isp::class);
    }
}
