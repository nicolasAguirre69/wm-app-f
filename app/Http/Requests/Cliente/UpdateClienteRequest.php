<?php

namespace App\Http\Requests\Cliente;

use App\Models\Isp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Usamos el ISP DEL CLIENTE (no el del usuario): así el Super Admin
        // puede editar clientes de cualquier ISP, validando contra los
        // catálogos de la ISP a la que pertenece ese cliente.
        $ispId = $this->route('cliente')->isp_id;

        return [
            // Código único DENTRO del ISP, contando también los servicios
            // eliminados: la base no permite repetirlo (índice único).
            'codigo_cliente' => [
                'required', 'string', 'max:50',
                Rule::unique('clientes', 'codigo_cliente')
                    ->where('isp_id', $ispId)
                    ->ignore($this->route('cliente')),
            ],
            // Solo datos del SERVICIO: los de la persona se editan en el titular
            // (TitularController), y aplican a todos sus servicios.

            // Ciudad: catálogo GLOBAL, solo debe existir. Ya no se guarda en el
            // cliente (sale del barrio); se usa para validar el barrio.
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')->whereNull('deleted_at'),
            ],
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

            // Opcional al editar: si no se sube uno nuevo, se conserva el actual.
            'documento_digitalizado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            'estado_id.exists' => 'Ese estado no está permitido en esta ISP.',
            'codigo_cliente.unique' => 'Ya existe un servicio con ese código en esta ISP (puede ser uno eliminado). Use otro código.',
            'barrio_id.exists' => 'El barrio no es válido o no pertenece a la ciudad seleccionada.',
            'dia_corte.between' => 'El día de corte debe estar entre 1 y 31.',
            'documento_digitalizado.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'documento_digitalizado.max' => 'El documento no puede superar 5 MB.',
        ];
    }
}
