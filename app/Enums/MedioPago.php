<?php

namespace App\Enums;

enum MedioPago: string
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Consignacion = 'consignacion';
    case Tarjeta = 'tarjeta';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Consignacion => 'Consignación',
            self::Tarjeta => 'Tarjeta',
            self::Otro => 'Otro',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $m) => ['value' => $m->value, 'label' => $m->label()], self::cases());
    }
}
