<?php

namespace App\Http\Requests\EstadoCliente;

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
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                Rule::unique('estados_cliente', 'nombre')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
            ],
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
        ];
    }
}
