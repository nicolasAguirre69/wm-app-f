<?php

namespace App\Console\Commands;

use App\Models\Scopes\IspScope;
use App\Models\Titular;
use Illuminate\Console\Command;

/**
 * Comando de mantenimiento: pone los nombres de TODOS los titulares existentes
 * en formato "Título". Reutiliza la misma lógica del modelo (hook 'saving'),
 * así que no duplica reglas. Útil para normalizar los clientes importados.
 *
 * Desde la normalización 3FN los nombres viven en `titulares` (la persona),
 * no en `clientes` (el servicio).
 */
class NormalizarNombresClientes extends Command
{
    protected $signature = 'clientes:normalizar-nombres';

    protected $description = 'Normaliza a formato Título los nombres de todos los titulares existentes.';

    public function handle(): int
    {
        $total = 0;
        $cambiados = 0;

        // withoutGlobalScope(IspScope) para recorrer TODAS las ISP (en consola
        // no hay usuario autenticado). Los borrados lógicos siguen excluidos.
        Titular::withoutGlobalScope(IspScope::class)
            ->chunkById(500, function ($titulares) use (&$total, &$cambiados): void {
                foreach ($titulares as $titular) {
                    $total++;
                    // save() dispara el hook 'saving' del modelo, que normaliza.
                    $titular->save();

                    if ($titular->wasChanged()) {
                        $cambiados++;
                    }
                }
            });

        $this->info("Titulares revisados: {$total}. Normalizados: {$cambiados}.");

        return self::SUCCESS;
    }
}
