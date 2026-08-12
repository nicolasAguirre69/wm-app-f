<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use App\Models\Scopes\IspScope;
use Illuminate\Console\Command;

/**
 * Comando de mantenimiento: pone los nombres de TODOS los clientes existentes
 * en formato "Título". Reutiliza la misma lógica del modelo (hook 'saving'),
 * así que no duplica reglas. Útil para normalizar los clientes importados.
 */
class NormalizarNombresClientes extends Command
{
    protected $signature = 'clientes:normalizar-nombres';

    protected $description = 'Normaliza a formato Título los nombres de todos los clientes existentes.';

    public function handle(): int
    {
        $total = 0;
        $cambiados = 0;

        // withoutGlobalScope(IspScope) para recorrer TODAS las ISP (en consola
        // no hay usuario autenticado). Los borrados lógicos siguen excluidos.
        Cliente::withoutGlobalScope(IspScope::class)
            ->chunkById(500, function ($clientes) use (&$total, &$cambiados): void {
                foreach ($clientes as $cliente) {
                    $total++;
                    // save() dispara el hook 'saving' del modelo, que normaliza.
                    $cliente->save();

                    if ($cliente->wasChanged()) {
                        $cambiados++;
                    }
                }
            });

        $this->info("Clientes revisados: {$total}. Normalizados: {$cambiados}.");

        return self::SUCCESS;
    }
}
