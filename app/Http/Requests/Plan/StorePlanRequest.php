<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
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
            'tipo_plan_id' => [
                'required',
                'exists:tipos_plan,id',
                // Un plan es único por (tipo_plan + tipo_servicio + Mbps)
                // dentro del ISP. Adjuntamos la regla a este campo.
                Rule::unique('planes', 'tipo_plan_id')
                    ->where('isp_id', $this->user()->isp_id)
                    ->where('tipo_servicio_id', $this->input('tipo_servicio_id'))
                    ->where('cantidad', $this->input('cantidad') ?: null)
                    ->whereNull('deleted_at'),
            ],
            'tipo_servicio_id' => ['required', 'exists:tipos_servicio,id'],
            // Mbps: opcional (un plan de solo TV no tiene velocidad).
            'cantidad' => ['nullable', 'integer', 'min:1'],
            // Precio: obligatorio, no negativo.
            'valor' => ['required', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_plan_id.required' => 'Debes seleccionar un tipo de plan.',
            'tipo_plan_id.exists' => 'El tipo de plan no es válido.',
            'tipo_plan_id.unique' => 'Ya existe un plan con ese tipo, servicio y velocidad.',
            'tipo_servicio_id.required' => 'Debes seleccionar un tipo de servicio.',
            'tipo_servicio_id.exists' => 'El tipo de servicio no es válido.',
            'cantidad.integer' => 'La cantidad (Mbps) debe ser un número entero.',
            'valor.required' => 'El valor es obligatorio.',
            'valor.numeric' => 'El valor debe ser un número.',
        ];
    }
}
