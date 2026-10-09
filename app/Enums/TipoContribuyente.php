<?php

namespace App\Enums;

/**
 * Tipos de contribuyente: los mismos del sistema de facturación
 * (tabla tipo_contribuyente: 0 Regimen Comun, 1 Regimen Simplificado,
 * 2 Gran Contribuyente, 3 Tercero Exterior).
 *
 * No lo elige el usuario: se deduce del tipo de identificación
 * (ver paraIdentificacion) cada vez que se guarda un titular.
 */
enum TipoContribuyente: string
{
    case RegimenComun = 'regimen_comun';
    case RegimenSimplificado = 'regimen_simplificado';
    case GranContribuyente = 'gran_contribuyente';
    case TerceroExterior = 'tercero_exterior';

    /**
     * Nombre tal como aparece en el sistema de facturación.
     */
    public function label(): string
    {
        return match ($this) {
            self::RegimenComun => 'Regimen Comun',
            self::RegimenSimplificado => 'Regimen Simplificado',
            self::GranContribuyente => 'Gran Contribuyente',
            self::TerceroExterior => 'Tercero Exterior',
        };
    }

    /**
     * id_tipo_contribuyente en el sistema de facturación.
     */
    public function codigo(): int
    {
        return match ($this) {
            self::RegimenComun => 0,
            self::RegimenSimplificado => 1,
            self::GranContribuyente => 2,
            self::TerceroExterior => 3,
        };
    }

    /**
     * Regla del negocio según el tipo de identificación:
     *   CC, TI            -> Regimen Comun
     *   NIT               -> Gran Contribuyente
     *   CE, PA, PPT, PEP  -> Tercero Exterior (documentos de extranjeros)
     */
    public static function paraIdentificacion(TipoIdentificacion|string|null $tipo): self
    {
        $tipo = $tipo instanceof TipoIdentificacion ? $tipo : TipoIdentificacion::tryFrom((string) $tipo);

        return match ($tipo) {
            TipoIdentificacion::NIT => self::GranContribuyente,
            TipoIdentificacion::CE,
            TipoIdentificacion::PA,
            TipoIdentificacion::PPT,
            TipoIdentificacion::PEP => self::TerceroExterior,
            default => self::RegimenComun,
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
