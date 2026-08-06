<?php

namespace App\Http\Requests\Cliente;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'codigo_cliente' => [
                'required', 'string', 'max:255',
                Rule::unique('clientes', 'codigo_cliente')
                    ->where('isp_id', $ispId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('cliente')),
            ],
            'tipo_identificacion' => ['required', Rule::enum(TipoIdentificacion::class)],
            'identificacion' => ['required', 'string', 'max:255'],
            'tipo_contribuyente' => ['required', Rule::enum(TipoContribuyente::class)],

            'primer_nombre' => ['required', 'string', 'max:255'],
            'segundo_nombre' => ['nullable', 'string', 'max:255'],
            'primer_apellido' => ['required', 'string', 'max:255'],
            'segundo_apellido' => ['nullable', 'string', 'max:255'],

            'telefono_1' => ['required', 'string', 'max:255'],
            'telefono_2' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],

            // Ciudad: catálogo GLOBAL, solo debe existir.
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

            'plan_id' => [
                'required',
                Rule::exists('planes', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
            ],
            'estado_id' => [
                'required',
                Rule::exists('estados_cliente', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
            ],

            'fecha_instalacion' => ['nullable', 'date'],
            'dia_corte' => ['nullable', 'integer', 'between:1,31'],

            // Opcional al editar: si no se sube uno nuevo, se conserva el actual.
            'documento_digitalizado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'codigo_cliente.unique' => 'Ya existe un cliente con ese código en tu ISP.',
            'barrio_id.exists' => 'El barrio no es válido o no pertenece a la ciudad seleccionada.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'dia_corte.between' => 'El día de corte debe estar entre 1 y 31.',
            'documento_digitalizado.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'documento_digitalizado.max' => 'El documento no puede superar 5 MB.',
        ];
    }
}
