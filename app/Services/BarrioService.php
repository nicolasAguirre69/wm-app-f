<?php

namespace App\Services;

use App\Models\Barrio;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lógica de negocio de Barrios. Aislado por ISP vía BelongsToIsp.
 */
class BarrioService
{
    /**
     * Lista paginada de barrios, con búsqueda y ordenamiento.
     *
     * @param  array{search?: string, sort?: string, direction?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Barrio::query()
            // Eager loading: trae la ciudad de cada barrio SIN caer en N+1.
            ->with('ciudad')
            // withCount agrega 'redes_count' contando las redes de cada barrio
            // en la misma consulta (sin traer todas las filas de redes).
            ->withCount('redes')
            ->when(
                ! empty($filtros['search']),
                fn (Builder $q) => $q->where(function (Builder $sub) use ($filtros) {
                    $sub->where('nombre', 'like', '%'.$filtros['search'].'%')
                        ->orWhere('prefijo', 'like', '%'.$filtros['search'].'%');
                })
            )
            ->orderBy($filtros['sort'] ?? 'nombre', $filtros['direction'] ?? 'asc')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param  array{ciudad_id: int, nombre: string, prefijo: string, red: string}  $datos
     */
    public function crear(array $datos): Barrio
    {
        return Barrio::create($datos);
    }

    /**
     * @param  array{ciudad_id: int, nombre: string, prefijo: string, red: string}  $datos
     */
    public function actualizar(Barrio $barrio, array $datos): Barrio
    {
        $barrio->update($datos);

        return $barrio;
    }

    public function eliminar(Barrio $barrio): void
    {
        $barrio->delete();
    }
}
