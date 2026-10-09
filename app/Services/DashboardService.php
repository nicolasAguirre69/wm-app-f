<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
            'esGlobal' => false,
            'totalClientes' => Cliente::count(),

            // Servicios sobre puertos alquilados a una ISP externa (solo existe en
            // la ISP principal; en las ISP cliente no se envía).
            'puertosAlquilados' => Auth::user()?->isp?->esPrincipal()
                ? Cliente::where('puerto_alquilado', true)->count()
                : null,

            // Por fecha de instalación, no por la fecha en que se cargó el cliente.
            'nuevosEsteMes' => Cliente::whereYear('fecha_instalacion', Carbon::now()->year)
                ->whereMonth('fecha_instalacion', Carbon::now()->month)
                ->count(),

            // Un dato por estado (solo los marcados para estadísticas).
            'porEstado' => EstadoCliente::withCount('clientes')
                ->where('en_estadisticas', true)
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

            // Crecimiento: clientes instalados por mes (últimos 6 meses).
            'crecimiento' => $this->crecimientoUltimosMeses(6),
        ];
    }

    /**
     * Estadísticas globales para el Super Admin (todas las ISPs).
     * Su scope global ya está desactivado, así que los counts son de toda
     * la plataforma.
     *
     * @return array<string, mixed>
     */
    public function paraSuperAdmin(): array
    {
        return [
            'esGlobal' => true,
            'totalIsps' => Isp::count(),
            'totalClientes' => Cliente::count(),
            // Clientes listos para facturar (marcados como facturables).
            'facturables' => Cliente::where('facturable', true)->count(),
            // Servicios sobre puertos alquilados a una ISP externa.
            'puertosAlquilados' => Cliente::where('puerto_alquilado', true)->count(),
            // Por fecha de instalación, no por la fecha en que se cargó el cliente.
            'nuevosEsteMes' => Cliente::whereYear('fecha_instalacion', Carbon::now()->year)
                ->whereMonth('fecha_instalacion', Carbon::now()->month)
                ->count(),

            // Clientes por ISP.
            'porIsp' => Isp::withCount('clientes')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Isp $i) => ['nombre' => $i->nombre, 'total' => $i->clientes_count])
                ->sortByDesc('total')
                ->values(),

            // Clientes por estado GLOBAL (agrupados por nombre de estado).
            // Partimos de los ESTADOS con leftJoin a clientes, para incluir
            // también los estados que aún no tienen ningún cliente (total 0).
            'porEstadoGlobal' => EstadoCliente::query()
                ->leftJoin('clientes', function ($join) {
                    $join->on('clientes.estado_id', '=', 'estados_cliente.id')
                        ->whereNull('clientes.deleted_at');
                })
                ->where('estados_cliente.en_estadisticas', true)
                ->selectRaw('estados_cliente.nombre as nombre, MIN(estados_cliente.color) as color, count(clientes.id) as total')
                ->groupBy('estados_cliente.nombre')
                ->orderBy('estados_cliente.nombre')
                ->get()
                ->map(fn ($r) => ['nombre' => $r->nombre, 'color' => $r->color, 'total' => (int) $r->total]),

            // Desglose por ISP de cada estado (para el modal).
            'desglosePorEstado' => Cliente::query()
                ->join('estados_cliente', 'clientes.estado_id', '=', 'estados_cliente.id')
                ->join('isps', 'clientes.isp_id', '=', 'isps.id')
                ->whereNull('estados_cliente.deleted_at')
                ->where('estados_cliente.en_estadisticas', true)
                ->selectRaw('estados_cliente.nombre as estado, isps.nombre as isp, count(*) as total')
                ->groupBy('estados_cliente.nombre', 'isps.nombre')
                ->orderBy('isps.nombre')
                ->get()
                ->groupBy('estado')
                ->map(fn ($filas) => $filas->map(fn ($f) => ['nombre' => $f->isp, 'total' => (int) $f->total])->values()),

            // Crecimiento: clientes instalados por mes (últimos 6 meses).
            'crecimiento' => $this->crecimientoUltimosMeses(6),
        ];
    }

    /**
     * Clientes instalados por mes en los últimos N meses (incluye meses en cero).
     * Se agrupa por fecha_instalacion; los clientes sin esa fecha no cuentan.
     *
     * @return \Illuminate\Support\Collection<int, array{mes: string, total: int}>
     */
    private function crecimientoUltimosMeses(int $meses)
    {
        $abreviaturas = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
        ];

        // Conteo agrupado por 'YYYY-MM'. MySQL/MariaDB usan DATE_FORMAT; SQLite
        // (las pruebas) no la tiene y usa strftime.
        $mes = in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)
            ? "DATE_FORMAT(fecha_instalacion, '%Y-%m')"
            : "strftime('%Y-%m', fecha_instalacion)";

        $conteos = Cliente::selectRaw("{$mes} as ym, count(*) as total")
            ->where('fecha_instalacion', '>=', Carbon::now()->subMonthsNoOverflow($meses - 1)->startOfMonth())
            ->where('fecha_instalacion', '<=', Carbon::now()->endOfMonth())
            ->groupBy('ym')
            ->pluck('total', 'ym');

        // Recorremos los meses en orden y rellenamos los que no tengan clientes.
        return collect(range($meses - 1, 0))->map(function (int $i) use ($conteos, $abreviaturas) {
            $fecha = Carbon::now()->subMonthsNoOverflow($i);
            $ym = $fecha->format('Y-m');

            return [
                'mes' => $abreviaturas[$fecha->format('m')].' '.$fecha->format('Y'),
                'total' => (int) ($conteos[$ym] ?? 0),
            ];
        })->values();
    }
}

