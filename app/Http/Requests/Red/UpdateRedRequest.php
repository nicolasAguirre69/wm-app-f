<?php

namespace App\Http\Requests\Red;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRedRequest extends FormRequest
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
            'barrio_id' => [
                'required',
                Rule::exists('barrios', 'id')
                    ->where('isp_id', $this->user()->isp_id)
                    ->whereNull('deleted_at'),
            ],
            'numero' => [
                'required',
                'integer',
                'between:1,16',
                Rule::unique('redes', 'numero')
                    ->where('isp_id', $this->user()->isp_id)
                    ->where('barrio_id', $this->input('barrio_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('red')),
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
