<?php

namespace App\Http\Requests\Cliente;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
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
        $ispId = $this->user()->isp_id;

        return [
            // Código único DENTRO del ISP.
            'codigo_cliente' => [
                'required', 'string', 'max:255',
                Rule::unique('clientes', 'codigo_cliente')
                    ->where('isp_id', $ispId)
                    ->whereNull('deleted_at'),
            ],
            'tipo_identificacion' => ['required', Rule::enum(TipoIdentificacion::class)],
            'identificacion' => ['required', 'string', 'max:255'],
            'tipo_contribuyente' => ['required', Rule::enum(TipoContribuyente::class)],

            'primer_nombre' => ['required', 'string', 'max:255'],
            'segundo_nombre' => ['nullable', 'string', 'max:255'],
            'primer_apellido' => ['required', 'string', 'max:255'],
            'segundo_apellido' => ['required', 'string', 'max:255'],

            'telefono_1' => ['required', 'string', 'max:255'],
            'telefono_2' => ['nullable', 'string', 'max:255'],
            'correo' => ['required', 'email', 'max:255'],

            // Ciudad: debe ser del ISP del usuario.
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
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

            'plan_id' => [
                'required',
                Rule::exists('planes', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
            ],
            'estado_id' => [
                'required',
                Rule::exists('estados_cliente', 'id')->where('isp_id', $ispId)->whereNull('deleted_at'),
            ],

            'fecha_instalacion' => ['required', 'date'],
            'dia_corte' => ['required', 'integer', 'between:1,31'],

            // Documento: archivo PDF o imagen, máx 5 MB.
            'documento_digitalizado' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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
            'documento_digitalizado.required' => 'Debes adjuntar el documento digitalizado.',
            'documento_digitalizado.mimes' => 'El documento debe ser PDF, JPG o PNG.',
            'documento_digitalizado.max' => 'El documento no puede superar 5 MB.',
        ];
    }
}
