<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Global Scope que aísla los datos por ISP (multi-tenancy).
 *
 * Se aplica automáticamente a TODAS las consultas de cualquier modelo que
 * use el trait BelongsToIsp. Añade "WHERE isp_id = X" de forma transparente,
 * donde X es el ISP del usuario autenticado.
 *
 * NO filtra en dos casos:
 *   1. No hay usuario autenticado (consola, seeders, jobs) → el código de
 *      sistema decide qué datos tocar.
 *   2. El usuario es Super Admin → tiene acceso global a toda la plataforma.
 */
class IspScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Sin usuario autenticado (consola/seeder): no filtramos.
        if (! Auth::check()) {
            return;
        }

        $user = Auth::user();

        // El Super Admin ve todo: no aplicamos el filtro.
        if ($user->is_super_admin) {
            return;
        }

        // Usuario normal: solo ve los registros de su propio ISP.
        $builder->where($model->getTable() . '.isp_id', $user->isp_id);
    }
}
