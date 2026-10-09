@php
    /** @var \App\Models\Pago $pago */
    $nombre = $titular
        ? trim(collect([$titular->primer_nombre, $titular->segundo_nombre, $titular->primer_apellido, $titular->segundo_apellido])->filter()->implode(' '))
        : '—';
    $tipoId = $titular?->tipo_identificacion?->value ?? '';
    $pesos = fn ($v) => '$ '.number_format((float) $v, 0, ',', '.');
    // Logo subido en ISPs; si la ISP principal no tiene, se usa el de Web Master
    // que viene con la aplicación (resources/images/logo-principal.png).
    $archivoPrincipal = resource_path('images/logo-principal.png');
    $logo = $isp->logo?->dataUri()
        ?? ($isp->esPrincipal() && is_file($archivoPrincipal)
            ? 'data:image/png;base64,'.base64_encode(file_get_contents($archivoPrincipal))
            : null);
    $direccion = collect([$servicio?->direccion, $servicio?->barrio?->nombre, $servicio?->barrio?->ciudad?->nombre])->filter()->implode(' · ') ?: '—';
    $anulado = $pago->estaAnulado();
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante {{ $pago->numeroComprobante() }}</title>
    <style>
        /* Colores de la marca: azul cian del logo sobre azul noche. */
        @page { margin: 0; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10px; color: #1e293b; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        td { vertical-align: top; }

        .encabezado { background: #0b1f3a; color: #ffffff; padding: 26px 40px 22px 40px; }
        .encabezado .logo { height: 78px; }
        .encabezado .empresa { font-size: 15px; font-weight: bold; color: #ffffff; }
        .encabezado .dato { font-size: 9.5px; color: #cbd5e1; line-height: 1.6; }
        .franja { height: 5px; background: #00aefb; }

        .contenido { padding: 22px 40px 0 40px; }

        .titulo { font-size: 19px; font-weight: bold; color: #0b1f3a; letter-spacing: 0.5px; }
        .subtitulo { font-size: 9.5px; color: #64748b; margin-top: 3px; }
        .numero { font-size: 15px; font-weight: bold; color: #00aefb; text-align: right; }
        .estado { display: inline-block; margin-top: 4px; padding: 3px 12px; border-radius: 10px; font-size: 9px; font-weight: bold; letter-spacing: 1px; }
        .estado-pagado { background: #dcfce7; color: #15803d; }
        .estado-anulado { background: #fee2e2; color: #b91c1c; }

        .resumen { margin-top: 16px; background: #e6f7ff; border: 1px solid #b3e5fd; border-radius: 8px; }
        .resumen td { padding: 14px 18px; }
        .resumen .etiqueta { font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; color: #0369a1; }
        .resumen .valor-grande { font-size: 26px; font-weight: bold; color: #0b1f3a; margin-top: 2px; }
        .resumen .letras { font-size: 8.5px; color: #475569; margin-top: 2px; }
        .resumen .dato-grande { font-size: 13px; font-weight: bold; color: #0b1f3a; margin-top: 2px; }
        .resumen .separador { border-left: 1px solid #b3e5fd; }

        .tarjetas { margin-top: 16px; }
        .tarjeta { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; }
        .tarjeta-titulo { font-size: 8.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #00aefb; margin-bottom: 8px; }
        .fila td { padding: 2.5px 0; }
        .fila .etiqueta { color: #64748b; width: 82px; }
        .fila .valor { color: #0f172a; font-weight: bold; }

        .detalle { margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 8px; }
        .detalle th { background: #0b1f3a; color: #ffffff; text-align: left; font-size: 8.5px; text-transform: uppercase; letter-spacing: 1px; padding: 8px 14px; }
        .detalle td { padding: 11px 14px; border-bottom: 1px solid #e2e8f0; }
        .derecha, .detalle th.derecha { text-align: right; }
        .detalle .total td { background: #f8fafc; border-bottom: none; font-size: 12px; font-weight: bold; color: #0b1f3a; }
        .detalle .total .monto { color: #00aefb; font-size: 14px; }

        .nota { margin-top: 12px; padding: 9px 12px; background: #f8fafc; border-left: 3px solid #00aefb; color: #334155; }
        .aviso-anulado { margin-top: 12px; padding: 9px 12px; background: #fef2f2; border-left: 3px solid #dc2626; color: #991b1b; }

        .gracias { margin-top: 22px; text-align: center; font-size: 12px; font-weight: bold; color: #0b1f3a; }
        .pie { position: fixed; bottom: 0; left: 0; right: 0; }
        .pie-texto { padding: 10px 40px; font-size: 8.5px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; }
        .pie-franja { height: 8px; background: #0b1f3a; border-top: 3px solid #00aefb; }

        .sello-anulado { position: fixed; top: 330px; left: 0; right: 0; text-align: center; font-size: 96px; font-weight: bold; color: #dc2626; opacity: 0.14; transform: rotate(-24deg); }
    </style>
</head>
<body>
@if ($anulado)
    <div class="sello-anulado">ANULADO</div>
@endif

{{-- Encabezado con los colores de la empresa --}}
<div class="encabezado">
    <table>
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                @if ($logo)
                    <img src="{{ $logo }}" class="logo" alt="">
                @else
                    <div class="empresa" style="font-size: 20px;">{{ $isp->nombre }}</div>
                @endif
            </td>
            <td style="width: 50%; text-align: right; vertical-align: middle;">
                {{-- Sin logo, el nombre ya va a la izquierda: aquí solo los datos. --}}
                @if ($logo)
                    <div class="empresa">{{ $isp->nombre }}</div>
                @endif
                <div class="dato">
                    @if ($isp->nit) NIT {{ $isp->nit }}<br> @endif
                    @if ($isp->direccion) {{ $isp->direccion }}<br> @endif
                    @if ($isp->telefono) Tel. {{ $isp->telefono }} @endif
                </div>
            </td>
        </tr>
    </table>
</div>
<div class="franja"></div>

<div class="contenido">
    {{-- Título, número y estado --}}
    <table>
        <tr>
            <td>
                <div class="titulo">COMPROBANTE DE PAGO</div>
                <div class="subtitulo">Mensualidad del servicio {{ $servicio->codigo_cliente ?? '' }}</div>
            </td>
            <td class="derecha">
                <div class="numero">N.° {{ $pago->numeroComprobante() }}</div>
                @if ($anulado)
                    <span class="estado estado-anulado">ANULADO</span>
                @else
                    <span class="estado estado-pagado">PAGADO</span>
                @endif
            </td>
        </tr>
    </table>

    {{-- Lo importante, de un vistazo --}}
    <table class="resumen">
        <tr>
            <td style="width: 46%;">
                <div class="etiqueta">Valor pagado</div>
                <div class="valor-grande">{{ $pesos($pago->valor) }}</div>
                <div class="letras">{{ $pago->valorEnLetras() }}</div>
            </td>
            <td class="separador" style="width: 27%;">
                <div class="etiqueta">Mes pagado</div>
                <div class="dato-grande">{{ $pago->periodoTexto() }}</div>
            </td>
            <td class="separador" style="width: 27%;">
                <div class="etiqueta">Fecha de pago</div>
                <div class="dato-grande">{{ $pago->fecha_pago->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    {{-- Cliente y servicio --}}
    <table class="tarjetas">
        <tr>
            <td style="width: 49%;">
                <div class="tarjeta">
                    <div class="tarjeta-titulo">Cliente</div>
                    <table class="fila">
                        <tr><td class="etiqueta">Nombre</td><td class="valor">{{ $nombre }}</td></tr>
                        <tr><td class="etiqueta">{{ $tipoId ?: 'Documento' }}</td><td class="valor">{{ $titular->identificacion ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Teléfono</td><td class="valor">{{ $titular->telefono_1 ?? '—' }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="width: 2%;"></td>
            <td style="width: 49%;">
                <div class="tarjeta">
                    <div class="tarjeta-titulo">Servicio</div>
                    <table class="fila">
                        <tr><td class="etiqueta">Código</td><td class="valor">{{ $servicio->codigo_cliente ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Plan</td><td class="valor">{{ $pago->plan ?? '—' }}</td></tr>
                        <tr><td class="etiqueta">Dirección</td><td class="valor">{{ $direccion }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    {{-- Detalle --}}
    <table class="detalle">
        <thead>
            <tr>
                <th>Concepto</th>
                <th>Periodo</th>
                <th class="derecha">Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Mensualidad{{ $pago->plan ? ' — '.$pago->plan : '' }}</td>
                <td>{{ $pago->periodoTexto() }}</td>
                <td class="derecha">{{ $pesos($pago->valor) }}</td>
            </tr>
            <tr class="total">
                <td colspan="2">Total pagado</td>
                <td class="derecha monto">{{ $pesos($pago->valor) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- Cómo se pagó --}}
    <table class="tarjetas">
        <tr>
            <td>
                <div class="tarjeta">
                    <table class="fila">
                        <tr>
                            <td class="etiqueta">Medio de pago</td>
                            <td class="valor" style="width: 34%;">{{ $pago->medio_pago->label() }}</td>
                            <td class="etiqueta">Referencia</td>
                            <td class="valor">{{ $pago->referencia ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="etiqueta">Recibido por</td>
                            <td class="valor">{{ $pago->registrador?->name ?? '—' }}</td>
                            <td class="etiqueta">Registrado</td>
                            <td class="valor">{{ $pago->created_at?->format('d/m/Y H:i') }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    @if ($pago->observacion)
        <div class="nota"><strong>Observación:</strong> {{ $pago->observacion }}</div>
    @endif

    @if ($anulado)
        <div class="aviso-anulado">
            <strong>Este comprobante fue anulado</strong> el {{ $pago->anulado_at->format('d/m/Y H:i') }}
            por {{ $pago->anulador?->name ?? '—' }}. Motivo: {{ $pago->motivo_anulacion }}
        </div>
    @else
        <div class="gracias">¡Gracias por su pago!</div>
    @endif
</div>

<div class="pie">
    <div class="pie-texto">
        Este comprobante certifica el pago de la mensualidad indicada. Consérvelo para cualquier reclamo.
        @if ($isp->telefono) Atención al cliente: {{ $isp->telefono }}. @endif
        <br>{{ $isp->nombre }} · Generado el {{ now()->format('d/m/Y H:i') }}
    </div>
    <div class="pie-franja"></div>
</div>
</body>
</html>
