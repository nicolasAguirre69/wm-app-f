<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    // Aporta authorize() y authorizeResource() a todos los controladores.
    use AuthorizesRequests;

    /**
     * Desempaqueta los filtros del parámetro opaco ?f= (base64 de un JSON).
     * Solo conserva las claves permitidas y valores escalares, para no confiar
     * en entrada arbitraria del cliente.
     *
     * @param  list<string>  $permitidos
     * @return array<string, string>
     */
    protected function filtrosDe(Request $request, array $permitidos): array
    {
        $raw = $request->input('f');

        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $json = base64_decode($raw, true);
        $datos = $json ? json_decode($json, true) : null;

        if (! is_array($datos)) {
            return [];
        }

        return collect($datos)
            ->only($permitidos)
            ->filter(fn ($v) => is_string($v) || is_numeric($v))
            ->map(fn ($v) => (string) $v)
            ->all();
    }
}
