<?php

namespace App\Http\Requests\Ciudad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCiudadRequest extends FormRequest
{
    /**
     * La autorización real (¿puede crear ciudades?) la maneja la Policy en
     * el controlador. Aquí devolvemos true para no duplicar esa lógica.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para CREAR una ciudad.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'max:255',
                // Único DENTRO del ISP del usuario (no globalmente).
                Rule::unique('ciudades', 'nombre')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * Mensajes de error personalizados y claros para el usuario.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la ciudad es obligatorio.',
            'nombre.unique' => 'Ya existe una ciudad con ese nombre en tu ISP.',
        ];
    }
}
