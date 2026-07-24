<?php

namespace App\Services;

use App\Models\Ciudad;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lógica de negocio de Ciudades.
 *
 * El controlador delega aquí. Gracias al trait BelongsToIsp del modelo, todas
 * estas operaciones ya están aisladas por ISP automáticamente.
 */
class CiudadService
{
    /**
     * Lista paginada de ciudades, con búsqueda y ordenamiento opcionales.
     *
     * @param  array{search?: string, sort?: string, direction?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Ciudad::query()
            // Búsqueda por nombre (solo si viene el parámetro).
            ->when(
                ! empty($filtros['search']),
                fn (Builder $q) => $q->where('nombre', 'like', '%'.$filtros['search'].'%')
            )
            // Ordenamiento (por defecto: nombre ascendente).
            ->orderBy(
                $filtros['sort'] ?? 'nombre',
                $filtros['direction'] ?? 'asc'
            )
            ->paginate(10)
            // withQueryString conserva los filtros al cambiar de página.
            ->withQueryString();
    }

    /**
     * Crea una ciudad. El isp_id lo rellena el trait BelongsToIsp.
     *
     * @param  array{nombre: string}  $datos
     */
    public function crear(array $datos): Ciudad
    {
        return Ciudad::create($datos);
    }

    /**
     * Actualiza una ciudad existente.
     *
     * @param  array{nombre: string}  $datos
     */
    public function actualizar(Ciudad $ciudad, array $datos): Ciudad
    {
        $ciudad->update($datos);

        return $ciudad;
    }

    /**
     * Elimina (soft delete) una ciudad.
     */
    public function eliminar(Ciudad $ciudad): void
    {
        $ciudad->delete();
    }
}
