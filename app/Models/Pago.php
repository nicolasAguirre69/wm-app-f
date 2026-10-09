<?php

namespace App\Models;

use App\Enums\MedioPago;
use App\Models\Scopes\IspScope;
use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Pago de un servicio (cliente) para un mes (periodo). Genera un comprobante
 * en PDF con número consecutivo por ISP.
 *
 * No se borra ni se edita: si hubo un error se ANULA con su motivo y se
 * registra de nuevo (trazabilidad).
 */
class Pago extends Model
{
    use BelongsToIsp, HasHashid;

    protected $table = 'pagos';

    protected $appends = ['hashid'];

    protected $fillable = [
        'isp_id', 'numero', 'cliente_id', 'periodo', 'valor', 'fecha_pago', 'medio_pago',
        'referencia', 'observacion', 'plan', 'registrado_por',
        'anulado_at', 'anulado_por', 'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'periodo' => 'date',
            'fecha_pago' => 'date',
            'valor' => 'decimal:2',
            'medio_pago' => MedioPago::class,
            'anulado_at' => 'datetime',
        ];
    }

    /**
     * Registra el pago con el siguiente número de comprobante de su ISP (en
     * una transacción, para que el número no se repita).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function registrar(Cliente $servicio, array $datos, ?int $userId): self
    {
        return DB::transaction(function () use ($servicio, $datos, $userId) {
            $ultimo = static::withoutGlobalScope(IspScope::class)
                ->where('isp_id', $servicio->isp_id)
                ->lockForUpdate()
                ->max('numero');

            return static::create([
                ...$datos,
                'isp_id' => $servicio->isp_id,
                'cliente_id' => $servicio->id,
                'numero' => ((int) $ultimo) + 1,
                'plan' => self::nombrePlan($servicio->plan),
                'registrado_por' => $userId,
            ]);
        });
    }

    /** "Hogar - 300 Mbps Internet + TV" (igual que en la lista de Clientes). */
    public static function nombrePlan(?Plan $plan): ?string
    {
        if (! $plan) {
            return null;
        }

        $plan->loadMissing(['tipoPlan', 'tipoServicio']);
        $servicio = trim(($plan->cantidad ? "{$plan->cantidad} Mbps " : '').($plan->tipoServicio?->nombre ?? ''));

        return implode(' - ', array_filter([$plan->tipoPlan?->nombre, $servicio])) ?: null;
    }

    /** Pagos vigentes (no anulados). */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('anulado_at');
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_at !== null;
    }

    /** "RC-000123" */
    public function numeroComprobante(): string
    {
        return 'RC-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    /** "CUARENTA Y CINCO MIL PESOS M/CTE" (valor en letras del comprobante). */
    public function valorEnLetras(): string
    {
        $entero = (int) round((float) $this->valor);

        return mb_strtoupper(self::numeroALetras($entero).' pesos M/CTE');
    }

    public static function numeroALetras(int $n): string
    {
        if ($n === 0) {
            return 'cero';
        }

        $unidades = ['', 'un', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez',
            'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte',
            'veintiún', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $decenas = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $centenas = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];

        $menorMil = function (int $x) use ($unidades, $decenas, $centenas): string {
            if ($x === 100) {
                return 'cien';
            }
            $texto = $centenas[intdiv($x, 100)];
            $resto = $x % 100;
            if ($resto > 0) {
                $parte = $resto < 30
                    ? $unidades[$resto]
                    : $decenas[intdiv($resto, 10)].($resto % 10 ? ' y '.$unidades[$resto % 10] : '');
                $texto = trim($texto.' '.$parte);
            }

            return $texto;
        };

        $partes = [];
        $millones = intdiv($n, 1_000_000);
        $miles = intdiv($n % 1_000_000, 1000);
        $resto = $n % 1000;

        if ($millones > 0) {
            $partes[] = $millones === 1 ? 'un millón' : self::numeroALetras($millones).' millones';
        }
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'mil' : $menorMil($miles).' mil';
        }
        if ($resto > 0) {
            $partes[] = $menorMil($resto);
        }

        // "un millón de pesos" / "dos millones de pesos" cuando no hay miles ni unidades.
        $texto = implode(' ', $partes);

        return $millones > 0 && $miles === 0 && $resto === 0 ? $texto.' de' : $texto;
    }

    /** "Octubre 2026" */
    public function periodoTexto(): string
    {
        return self::textoPeriodo($this->periodo);
    }

    public static function textoPeriodo(Carbon $fecha): string
    {
        $meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $meses[$fecha->month - 1].' '.$fecha->year;
    }

    /** Servicio pagado (también si luego se eliminó). */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class)->withoutGlobalScope(IspScope::class)->withTrashed();
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }
}
