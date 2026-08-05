<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lógica de negocio de Planes. Aislado por ISP vía BelongsToIsp.
 */
class PlanService
{
    /**
     * @param  array{search?: string, sort?: string, direction?: string}  $filtros
     */
    public function listar(array $filtros): LengthAwarePaginator
    {
        return Plan::query()
            // Cargamos los dos catálogos para mostrarlos sin caer en N+1.
            ->with(['tipoPlan', 'tipoServicio'])
            ->when(
                ! empty($filtros['search']),
                // Agrupamos el OR en un where(function) para que NO rompa el
                // filtro por isp_id del Global Scope.
                fn (Builder $q) => $q->where(function (Builder $grupo) use ($filtros) {
                    $grupo->whereHas(
                        'tipoServicio',
                        fn (Builder $sub) => $sub->where('nombre', 'like', '%'.$filtros['search'].'%')
                    )->orWhereHas(
                        'tipoPlan',
                        fn (Builder $sub) => $sub->where('nombre', 'like', '%'.$filtros['search'].'%')
                    );
                })
            )
            ->orderBy($filtros['sort'] ?? 'valor', $filtros['direction'] ?? 'asc')
            ->paginate(10)
            ->withQueryString();
    }

    /**
     * @param  array{tipo_plan_id: int, tipo_servicio_id: int, cantidad: ?int, valor: float, activo?: bool}  $datos
     */
    public function crear(array $datos): Plan
    {
        return Plan::create($datos);
    }

    /**
     * @param  array{tipo_plan_id: int, tipo_servicio_id: int, cantidad: ?int, valor: float, activo?: bool}  $datos
     */
    public function actualizar(Plan $plan, array $datos): Plan
    {
        $plan->update($datos);

        return $plan;
    }

    public function eliminar(Plan $plan): void
    {
        $plan->delete();
    }
}
