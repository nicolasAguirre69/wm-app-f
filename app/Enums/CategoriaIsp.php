<?php

namespace App\Enums;

/**
 * Categoría de una ISP cliente: qué tanto administra de sus clientes.
 *
 *   - SoloTv: sus clientes solo tienen TV de Web Master. Plan TV automático,
 *     estados Activo / Retirado, sin menú de Planes.
 *   - GestionCompleta: vende sus propios planes (Internet, Internet + TV...),
 *     elige el plan de cada cliente y maneja Activo / Corte / Retirado. Web
 *     Master solo le factura los servicios con TV.
 *
 * La ISP principal no usa categoría: siempre tiene gestión completa.
 */
enum CategoriaIsp: string
{
    case SoloTv = 'solo_tv';
    case GestionCompleta = 'gestion_completa';

    public function label(): string
    {
        return match ($this) {
            self::SoloTv => 'Solo TV',
            self::GestionCompleta => 'Gestión completa',
        };
    }

    public function descripcion(): string
    {
        return match ($this) {
            self::SoloTv => 'Sus clientes solo tienen TV: el plan TV se asigna solo y los estados son Activo y Retirado.',
            self::GestionCompleta => 'Vende sus propios planes (Internet, Internet + TV...), elige el plan de cada cliente y puede usar el estado Corte. Web Master solo le factura los servicios con TV.',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, descripcion: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $c) => ['value' => $c->value, 'label' => $c->label(), 'descripcion' => $c->descripcion()],
            self::cases(),
        );
    }
}
