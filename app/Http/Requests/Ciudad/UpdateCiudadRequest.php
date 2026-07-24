<?php

namespace App\Http\Requests\Ciudad;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCiudadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas para EDITAR una ciudad.
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
                // Único dentro del ISP, pero IGNORANDO la ciudad que editamos
                // (si no, no podrías guardar sin cambiar el nombre).
                Rule::unique('ciudades', 'nombre')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('ciudad')),
            ],
        ];
    }

    /**
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
