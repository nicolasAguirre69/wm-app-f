<?php

namespace App\Http\Requests\Isp;

use App\Enums\CategoriaIsp;
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
            // Categoría de la ISP cliente (Solo TV / Gestión completa).
            'categoria' => ['nullable', Rule::enum(CategoriaIsp::class)],
            // Encabezado del comprobante de pago.
            'nit' => ['nullable', 'string', 'max:20'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'quitar_logo' => ['boolean'],
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
            'logo.image' => 'El logo debe ser una imagen PNG o JPG.',
            'logo.mimes' => 'El logo debe ser una imagen PNG o JPG.',
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
        ];
    }
}
