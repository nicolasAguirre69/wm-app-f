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
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
            'color.required' => 'El color es obligatorio.',
            'color.regex' => 'El color debe ser un valor hexadecimal (ej. #22c55e).',
        ];
    }
}
