<?php

namespace App\Enums;

/**
 * Tipos de identificación (Colombia). Lista fija del negocio.
 */
enum TipoIdentificacion: string
{
    case CC = 'CC';
    case CE = 'CE';
    case NIT = 'NIT';
    case PA = 'PA';
    case TI = 'TI';

    public function label(): string
    {
        return match ($this) {
            self::CC => 'Cédula de ciudadanía',
            self::CE => 'Cédula de extranjería',
            self::NIT => 'NIT',
            self::PA => 'Pasaporte',
            self::TI => 'Tarjeta de identidad',
        };
    }

    /**
     * Lista {value, label} para los selectores del frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(
            fn (self $caso) => ['value' => $caso->value, 'label' => $caso->label()],
            self::cases(),
        );
    }
}
