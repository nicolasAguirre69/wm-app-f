<?php

namespace App\Http\Requests\Cliente;

use App\Http\Requests\Concerns\ReglasTitular;
use App\Models\Isp;
use App\Models\Titular;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StoreClienteRequest extends FormRequest
{
    use ReglasTitular;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Titular existente al que se agrega el servicio ("Agregar servicio").
     * Null cuando es un cliente nuevo (la persona viene en el formulario).
     */
    private ?Titular $titularExistente = null;

    private bool $titularResuelto = false;

    public function titular(): ?Titular
    {
        if (! $this->titularResuelto) {
            $this->titularResuelto = true;
            $id = Titular::decodeHashid($this->input('titular'));
            // Consulta normal: respeta el aislamiento por ISP.
            $this->titularExistente = $id ? Titular::find($id) : null;
        }

        return $this->titularExistente;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarDatosTitular();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Si se agrega a un titular existente, el servicio va en SU ISP; si no,
        // en la del usuario.
        $titular = $this->titular();
        $ispId = $titular?->isp_id ?? $this->user()->isp_id;

        // Datos de la persona: solo para un cliente nuevo. Al agregar un
        // servicio a un titular existente se descartan (no se tocan sus datos).
        $persona = $titular
            ? array_fill_keys(Titular::CAMPOS, ['exclude'])
            : $this->reglasTitular($ispId);

        return [
            ...$persona,

            // Titular existente (hashid). Si viene, debe ser válido y visible.
            'titular' => [
                'nullable', 'string',
                function (string $atributo, mixed $valor, \Closure $fallar) use ($titular) {
                    if ($valor && ! $titular) {
                        $fallar('El titular no existe o no pertenece a tu ISP.');
                    }
                },
            ],

            // Código único DENTRO del ISP, contando también los servicios
            // eliminados: la base no permite repetirlo (índice único).
            'codigo_cliente' => [
                'required', 'string', 'max:50',
                Rule::unique('clientes', 'codigo_cliente')
                    ->where('isp_id', $ispId),
            ],
            // Ciudad: catálogo GLOBAL, solo debe existir. Ya no se guarda en el
            // cliente (sale del barrio); se usa para validar el barrio.
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')->whereNull('deleted_at'),
            ],
            // Barrio: del ISP Y de la ciudad seleccionada (validación cruzada).
            'barrio_id' => [
                'required',
                Rule::exists('barrios', 'id')
                    ->where('isp_id', $ispId)
                    ->where('ciudad_id', $this->input('ciudad_id'))
                    ->whereNull('deleted_at'),
            ],
            'direccion' => ['required', 'string', 'max:255'],

            // En una ISP cliente sin planes propios el plan es siempre el de TV
            // y lo asigna el sistema (ClienteService): no hace falta enviarlo.
            'plan_id' => [
                $this->planAutomatico($ispId) ? 'nullable' : 'required',
                Rule::exists('planes', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
            ],
            'estado_id' => [
                'required',
                $this->reglaEstado($ispId),
            ],

            // Puerto alquilado a una ISP externa: solo en la ISP principal. En
            // una ISP cliente se descarta (ClienteService lo deja en false).
            'puerto_alquilado' => $this->esIspCliente($ispId) ? ['exclude'] : ['nullable', 'boolean'],

            'fecha_instalacion' => ['nullable', 'date'],
            'dia_corte' => ['nullable', 'integer', 'between:1,31'],

            // Documento: obligatorio en la ISP principal; en una ISP cliente no se
            // pide. Si viene, debe ser PDF o imagen, máx 5 MB.
            'documento_digitalizado' => [
                $this->esIspCliente($ispId) ? 'nullable' : 'required',
                'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120',
            ],

            // Traslado: si el cliente ya existe en otra ISP, retirarlo allá.
            'trasladar' => ['nullable', 'boolean'],
        ];
    }

    /**
     * ISP cliente sin planes propios: el plan (TV) lo asigna ClienteService.
     */
    private function planAutomatico(?int $ispId): bool
    {
        return $ispId !== null && Isp::find($ispId)?->tienePlanesPropios() === false;
    }

    private function esIspCliente(?int $ispId): bool
    {
        return $ispId !== null && Isp::find($ispId)?->esPrincipal() === false;
    }

    /**
     * El estado debe ser del ISP y, si es una ISP cliente, solo Activo o
     * Retirado (EstadoCliente::PERMITIDOS_ISP_CLIENTE).
     */
    private function reglaEstado(?int $ispId): Exists
    {
        $regla = Rule::exists('estados_cliente', 'id')->where('isp_id', $ispId)->whereNull('deleted_at');

        $permitidos = $ispId ? Isp::find($ispId)?->estadosPermitidos() : null;

        if ($permitidos !== null) {
            $regla->whereIn('nombre', $permitidos);
        }

        return $regla;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->mensajesTitular(),
            'estado_id.exists' => 'Ese estado no está permitido en esta ISP.',
            'codigo_cliente.unique' => 'Ya existe un servicio con ese código en esta ISP (puede ser uno eliminado). Use otro código.',
            'barrio_id.exists' => 'El barrio no es válido o no pertenece a la ciudad seleccionada.',
            'dia_corte.between' => 'El día de corte debe estar entre 1 y 31.',
            'documento_digitalizado.required' => 'El documento digitalizado es obligatorio.',
            'documento_digitalizado.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'documento_digitalizado.max' => 'El documento no puede superar 5 MB.',
        ];
    }
}
