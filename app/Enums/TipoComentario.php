<?php

namespace App\Enums;

/**
 * Tipos de comentario de un cliente.
 */
enum TipoComentario: string
{
    case Seguimiento = 'seguimiento';
    case Facturacion = 'facturacion';

    public function label(): string
    {
        return match ($this) {
            self::Seguimiento => 'Seguimiento',
            self::Facturacion => 'Facturación',
        };
    }
}
