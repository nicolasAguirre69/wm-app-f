<?php

namespace App\Http\Requests\Red;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRedRequest extends FormRequest
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
            // El barrio debe existir y pertenecer al ISP del usuario.
            'barrio_id' => [
                'required',
                Rule::exists('barrios', 'id')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
            ],
            'numero' => [
                'required',
                'integer',
                'between:1,16', // Las redes siempre van del 1 al 16.
                // Único dentro del ISP + el barrio seleccionado.
                Rule::unique('redes', 'numero')
                    ->where('isp_id', $this->user()->isp_id)
                    ->where('barrio_id', $this->input('barrio_id'))
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
            'barrio_id.required' => 'Debes seleccionar un barrio.',
            'barrio_id.exists' => 'El barrio seleccionado no es válido.',
            'numero.required' => 'El número de la red es obligatorio.',
            'numero.between' => 'El número debe estar entre 1 y 16.',
            'numero.unique' => 'Ya existe una red con ese número en ese barrio.',
        ];
    }
}
