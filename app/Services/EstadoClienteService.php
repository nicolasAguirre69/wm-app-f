<?php

namespace App\Services;

use App\Models\EstadoCliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lógica de negocio de Estados de Cliente. Aislado por ISP vía BelongsToIsp.
 */
class EstadoClienteService
{
    /**
     * @param  array{search?: string, sort?: string, direction?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return EstadoCliente::query()
            ->when(
                ! empty($filtros['search']),
                fn (Builder $q) => $q->where('nombre', 'like', '%'.$filtros['search'].'%')
            )
            ->orderBy($filtros['sort'] ?? 'nombre', $filtros['direction'] ?? 'asc')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param  array{nombre: string}  $datos
     */
    public function crear(array $datos): EstadoCliente
    {
        return EstadoCliente::create($datos);
    }

    /**
     * @param  array{nombre: string}  $datos
     */
    public function actualizar(EstadoCliente $estado, array $datos): EstadoCliente
    {
        $estado->update($datos);

        return $estado;
    }

    public function eliminar(EstadoCliente $estado): void
    {
        $estado->delete();
    }
}
