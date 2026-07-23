<?php

namespace App\Enums;

/**
 * Tipos posibles de un ISP dentro de la plataforma.
 *
 * Un "backed enum" (enum respaldado por string) define un conjunto CERRADO
 * de valores válidos. Si intentas asignar cualquier otra cosa a la columna
 * `tipo`, PHP/Eloquent lanzará un error, evitando datos corruptos como
 * "principl" o "PRINCIPAL" por error de tipeo.
 */
enum TipoIsp: string
{
    case Principal = 'principal';
    case Cliente = 'cliente';

    /**
     * Etiqueta legible para mostrar en la interfaz.
     */
    public function label(): string
    {
        return match ($this) {
            self::Principal => 'ISP Principal',
            self::Cliente => 'ISP Cliente',
        };
    }
}
