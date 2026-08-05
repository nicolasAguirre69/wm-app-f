<?php

namespace App\Enums;

/**
 * Tipos de contribuyente (Colombia). Lista fija del negocio.
 */
enum TipoContribuyente: string
{
    case Natural = 'natural';
    case Juridica = 'juridica';
    case GranContribuyente = 'gran_contribuyente';
    case RegimenSimple = 'regimen_simple';
    case NoResponsableIva = 'no_responsable_iva';

    public function label(): string
    {
        return match ($this) {
            self::Natural => 'Persona natural',
            self::Juridica => 'Persona jurídica',
            self::GranContribuyente => 'Gran contribuyente',
            self::RegimenSimple => 'Régimen simple',
            self::NoResponsableIva => 'No responsable de IVA',
        };
    }

    /**
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
