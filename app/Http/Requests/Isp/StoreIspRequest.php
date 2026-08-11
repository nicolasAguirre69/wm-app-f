<?php

namespace App\Http\Requests\Isp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIspRequest extends FormRequest
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
                'required', 'string', 'max:255',
                Rule::unique('isps', 'nombre')->whereNull('deleted_at'),
            ],
            'activo' => ['boolean'],
            'id_producto' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del ISP es obligatorio.',
            'nombre.unique' => 'Ya existe un ISP con ese nombre.',
        ];
    }
}
