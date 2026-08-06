<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Plan;
use Illuminate\Support\Carbon;

/**
 * Estadísticas del dashboard. Todas las consultas quedan aisladas por ISP
 * automáticamente gracias al trait BelongsToIsp de los modelos.
 */
class DashboardService
{
    /**
     * Estadísticas para el dashboard de un ISP.
     *
     * @return array<string, mixed>
     */
    public function paraIsp(): array
    {
        return [
            'totalClientes' => Cliente::count(),

            'nuevosEsteMes' => Cliente::whereYear('created_at', Carbon::now()->year)
                ->whereMonth('created_at', Carbon::now()->month)
                ->count(),

            // Un dato por estado: nombre, color y cuántos clientes lo tienen.
            'porEstado' => EstadoCliente::withCount('clientes')
                ->orderBy('nombre')
                ->get()
                ->map(fn (EstadoCliente $e) => [
                    'nombre' => $e->nombre,
                    'color' => $e->color,
                    'total' => $e->clientes_count,
                ])
                ->values(),

            // Distribución por plan.
            'porPlan' => Plan::withCount('clientes')
                ->with(['tipoPlan', 'tipoServicio'])
                ->get()
                ->map(fn (Plan $p) => [
                    'nombre' => trim(
                        ($p->tipoPlan->nombre ?? '').' - '.($p->tipoServicio->nombre ?? '')
                        .($p->cantidad ? ' - '.$p->cantidad.'Mbps' : '')
                    ),
                    'total' => $p->clientes_count,
                ])
                ->sortByDesc('total')
                ->values(),
        ];
    }
}
