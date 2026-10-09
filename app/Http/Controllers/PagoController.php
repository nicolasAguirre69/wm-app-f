<?php

namespace App\Http\Controllers;

use App\Enums\MedioPago;
use App\Models\Cliente;
use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagos de un servicio y su comprobante en PDF. Solo ISP principal y
 * "Gestión completa" (PagoPolicy). Se usa desde las opciones del servicio
 * en Clientes (modal "Pagos"), por eso responde JSON.
 */
class PagoController extends Controller
{
    /**
     * Historial de pagos del servicio y valores sugeridos para registrar uno.
     */
    public function index(Request $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('viewAny', Pago::class);
        $this->authorize('view', $cliente);
        $this->exigirModulo($cliente);

        $cliente->loadMissing(['estado', 'plan']);

        $pagos = $cliente->pagos()
            ->with(['registrador:id,name', 'anulador:id,name'])
            ->orderByDesc('periodo')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'pagos' => $pagos->map(fn (Pago $p) => $this->resumen($p))->values(),
            'activo' => $this->estaActivo($cliente),
            'estado' => $cliente->estado?->nombre,
            'sugerido' => [
                'periodo' => $this->periodoSugerido($cliente, $pagos)->format('Y-m'),
                'valor' => $cliente->plan ? (string) (int) round((float) $cliente->plan->valor) : '',
                'fecha_pago' => now()->toDateString(),
            ],
            'medios' => MedioPago::opciones(),
            'puede' => [
                'registrar' => Gate::allows('create', Pago::class),
                // El módulo y la ISP ya se validaron arriba (exigirModulo / view).
                'anular' => $request->user()->is_super_admin || $request->user()->can('pagos.anular'),
            ],
        ]);
    }

    /**
     * Registra el pago de un mes. Solo si el servicio está Activo y ese mes no
     * tiene ya un pago vigente.
     */
    public function store(Request $request, Cliente $cliente): JsonResponse
    {
        $this->authorize('create', Pago::class);
        $this->authorize('view', $cliente);
        $this->exigirModulo($cliente);

        $cliente->loadMissing(['estado', 'plan']);

        if (! $this->estaActivo($cliente)) {
            throw ValidationException::withMessages([
                'periodo' => 'Solo se registran pagos de servicios en estado Activo (este está en '.($cliente->estado?->nombre ?? 'sin estado').').',
            ]);
        }

        $datos = $request->validate([
            'periodo' => ['required', 'date_format:Y-m'],
            'valor' => ['required', 'numeric', 'min:1', 'max:999999999'],
            'fecha_pago' => ['required', 'date', 'before_or_equal:today'],
            'medio_pago' => ['required', Rule::enum(MedioPago::class)],
            'referencia' => ['nullable', 'string', 'max:60'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ], [
            'periodo.required' => 'Elija el mes que se paga.',
            'valor.min' => 'El valor debe ser mayor a cero.',
            'fecha_pago.before_or_equal' => 'La fecha de pago no puede ser futura.',
        ]);

        $periodo = Carbon::createFromFormat('Y-m', $datos['periodo'])->startOfMonth();

        // No antes del mes de instalación.
        $instalacion = $cliente->fecha_instalacion ? Carbon::parse($cliente->fecha_instalacion)->startOfMonth() : null;
        if ($instalacion && $periodo->lt($instalacion)) {
            throw ValidationException::withMessages(['periodo' => 'El servicio se instaló en '.Pago::textoPeriodo($instalacion).': no se puede pagar un mes anterior.']);
        }

        // Un solo pago vigente por mes.
        $existente = $cliente->pagos()->vigentes()->whereDate('periodo', $periodo->toDateString())->first();
        if ($existente) {
            throw ValidationException::withMessages(['periodo' => Pago::textoPeriodo($periodo).' ya está pagado (comprobante '.$existente->numeroComprobante().'). Anúlelo si hay que corregirlo.']);
        }

        $pago = Pago::registrar($cliente, [
            ...$datos,
            'periodo' => $periodo->toDateString(),
        ], $request->user()->id);

        return response()->json([
            'mensaje' => "Pago registrado. Comprobante {$pago->numeroComprobante()}.",
            'pago' => $this->resumen($pago->load('registrador:id,name')),
        ], 201);
    }

    /**
     * Comprobante de pago en PDF (se abre en el navegador).
     */
    public function comprobante(Pago $pago): Response
    {
        $this->authorize('view', $pago);

        $pago->load(['cliente.titular', 'cliente.barrio.ciudad', 'isp.logo', 'registrador:id,name', 'anulador:id,name']);

        // dompdf guarda aquí la caché de fuentes; si la carpeta no existe, falla.
        File::ensureDirectoryExists(storage_path('fonts'));

        try {
            $pdf = Pdf::loadView('pdf.comprobante-pago', [
                'pago' => $pago,
                'isp' => $pago->isp,
                'servicio' => $pago->cliente,
                'titular' => $pago->cliente?->titular,
            ])->setPaper('letter');

            // Sin caché: si cambian los datos de la ISP o el pago se anula, el
            // navegador debe mostrar la versión nueva.
            return $pdf->stream($pago->numeroComprobante().'.pdf')
                ->header('Cache-Control', 'private, no-store, max-age=0');
        } catch (\Throwable $e) {
            // Queda en storage/logs/laravel.log con el detalle.
            report($e);

            abort(500, 'No se pudo generar el comprobante: '.$e->getMessage());
        }
    }

    /**
     * Anula un pago (no se borra: queda con su motivo).
     */
    public function anular(Request $request, Pago $pago): JsonResponse
    {
        $this->authorize('anular', $pago);

        if ($pago->estaAnulado()) {
            throw ValidationException::withMessages(['motivo' => 'Este pago ya está anulado.']);
        }

        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'max:500']],
            ['motivo.required' => 'Escriba por qué se anula el pago.'],
        );

        $pago->update([
            'anulado_at' => now(),
            'anulado_por' => $request->user()->id,
            'motivo_anulacion' => $datos['motivo'],
        ]);

        return response()->json([
            'mensaje' => "Comprobante {$pago->numeroComprobante()} anulado.",
            'pago' => $this->resumen($pago->load(['registrador:id,name', 'anulador:id,name'])),
        ]);
    }

    // --- Apoyo ---

    private function exigirModulo(Cliente $cliente): void
    {
        $cliente->loadMissing('isp');

        abort_unless((bool) $cliente->isp?->tienePagos(), 403, 'La ISP de este servicio no registra pagos.');
    }

    private function estaActivo(Cliente $cliente): bool
    {
        return $cliente->estado?->nombre === 'Activo';
    }

    /**
     * Mes sugerido: el siguiente al último pagado; si no hay pagos, el actual.
     *
     * @param  \Illuminate\Support\Collection<int, Pago>  $pagos
     */
    private function periodoSugerido(Cliente $cliente, $pagos): Carbon
    {
        $ultimo = $pagos->first(fn (Pago $p) => ! $p->estaAnulado());

        return $ultimo
            ? $ultimo->periodo->copy()->addMonthNoOverflow()->startOfMonth()
            : now()->startOfMonth();
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Pago $p): array
    {
        return [
            'id' => $p->id,
            'hashid' => $p->hashid,
            'numero' => $p->numeroComprobante(),
            'periodo' => $p->periodo->format('Y-m'),
            'periodo_texto' => $p->periodoTexto(),
            'valor' => (string) $p->valor,
            'fecha_pago' => $p->fecha_pago->toDateString(),
            'medio_pago' => $p->medio_pago->label(),
            'referencia' => $p->referencia,
            'observacion' => $p->observacion,
            'registrado_por' => $p->registrador?->name,
            'registrado_at' => $p->created_at?->format('Y-m-d H:i'),
            'anulado' => $p->estaAnulado(),
            'anulado_at' => $p->anulado_at?->format('Y-m-d H:i'),
            'anulado_por' => $p->anulador?->name,
            'motivo_anulacion' => $p->motivo_anulacion,
            'url' => "/pagos/{$p->hashid}/comprobante",
        ];
    }
}
