<?php

namespace App\Console\Commands;

use App\Models\Isp;
use App\Observers\IspObserver;
use Illuminate\Console\Command;

/**
 * Re-sincroniza los roles y permisos por defecto de todos los ISP con la lista
 * actual de permisos. Útil tras AGREGAR permisos nuevos al sistema: los ISP
 * creados antes no los tenían asignados en su rol Administrador.
 */
class SincronizarRolesIsp extends Command
{
    protected $signature = 'isp:sincronizar-roles';

    protected $description = 'Re-sincroniza los roles y permisos por defecto de todos los ISP.';

    public function handle(): int
    {
        $isps = Isp::all();

        foreach ($isps as $isp) {
            IspObserver::aplicarDefaults($isp);
            $this->line("  ✓ {$isp->nombre}");
        }

        $this->info("Roles sincronizados en {$isps->count()} ISP(s).");

        return self::SUCCESS;
    }
}
