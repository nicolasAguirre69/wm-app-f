<?php

namespace App\Services;

use App\Models\Red;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lógica de negocio de Redes. Aislado por ISP vía BelongsToIsp.
 */
class RedService
{
    /**
     * @param  array{search?: string, sort?: string, direction?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Red::query()
            // Cargamos el barrio y su ciudad para mostrarlos en la tabla.
            ->with('barrio.ciudad')
            ->when(
                ! empty($filtros['search']),
                fn (Builder $q) => $q->where('numero', 'like', '%'.$filtros['search'].'%')
            )
            ->orderBy($filtros['sort'] ?? 'numero', $filtros['direction'] ?? 'asc')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param  array{barrio_id: int, nombre: string}  $datos
     */
    public function crear(array $datos): Red
    {
        return Red::create($datos);
    }

    /**
     * @param  array{barrio_id: int, nombre: string}  $datos
     */
    public function actualizar(Red $red, array $datos): Red
    {
        $red->update($datos);

        return $red;
    }

    public function eliminar(Red $red): void
    {
        $red->delete();
    }
}
