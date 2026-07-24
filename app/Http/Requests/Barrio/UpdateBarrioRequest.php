<?php

namespace App\Http\Requests\Barrio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBarrioRequest extends FormRequest
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
            'ciudad_id' => [
                'required',
                Rule::exists('ciudades', 'id')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
            ],
            'nombre' => [
                'required',
                'string',
                'max:255',
                // Único dentro del ISP + ciudad, ignorando el barrio actual.
                Rule::unique('barrios', 'nombre')
                    ->where('isp_id', $this->user()->isp_id)
                    ->where('ciudad_id', $this->input('ciudad_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('barrio')),
            ],
            'prefijo' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ciudad_id.required' => 'Debes seleccionar una ciudad.',
            'ciudad_id.exists' => 'La ciudad seleccionada no es válida.',
            'nombre.required' => 'El nombre del barrio es obligatorio.',
            'nombre.unique' => 'Ya existe un barrio con ese nombre en esa ciudad.',
            'prefijo.required' => 'El prefijo es obligatorio.',
        ];
    }
}
