<?php

namespace App\Http\Requests\EstadoCliente;

use App\Models\Isp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEstadoClienteRequest extends FormRequest
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
        // En una ISP cliente solo existen los estados Activo y Retirado.
        $ispId = $this->user()->isp_id;
        $permitidos = $ispId ? Isp::find($ispId)?->estadosPermitidos() : null;

        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('estados_cliente', 'nombre')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
                ...($permitidos !== null ? [Rule::in($permitidos)] : []),
            ],
            'color' => ['required', Rule::in(\App\Models\EstadoCliente::COLORES)],
            'en_estadisticas' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del estado es obligatorio.',
            'nombre.unique' => 'Ya existe un estado con ese nombre en tu ISP.',
            'nombre.in' => 'En esta ISP solo se permiten los estados: '.implode(', ', Isp::find($this->user()->isp_id)?->estadosPermitidos() ?? []).'.',
            'color.required' => 'El color es obligatorio.',
            'color.in' => 'El color debe ser uno de la paleta estándar.',
        ];
    }
}
