<?php

namespace App\Enums;

enum PrioridadTicket: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';
    case Urgente = 'urgente';

    public function label(): string
    {
        return match ($this) {
            self::Baja => 'Baja',
            self::Media => 'Media',
            self::Alta => 'Alta',
            self::Urgente => 'Urgente',
        };
    }

    /** Color de la paleta universal (mismos colores que los estados de cliente). */
    public function color(): string
    {
        return match ($this) {
            self::Baja => '#6b7280',
            self::Media => '#3b82f6',
            self::Alta => '#f59e0b',
            self::Urgente => '#ef4444',
        };
    }

    /**
     * @return array<int, array{value: string, label: string, color: string}>
     */
    public static function opciones(): array
    {
        return array_map(fn (self $p) => ['value' => $p->value, 'label' => $p->label(), 'color' => $p->color()], self::cases());
    }
}
