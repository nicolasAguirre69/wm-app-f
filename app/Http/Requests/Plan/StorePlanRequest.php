<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

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
            // Catálogos GLOBALES: exists sin filtro de isp_id.
            'tipo_plan_id' => ['required', 'exists:tipos_plan,id'],
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
            'tipo_servicio_id.required' => 'Debes seleccionar un tipo de servicio.',
            'tipo_servicio_id.exists' => 'El tipo de servicio no es válido.',
            'cantidad.integer' => 'La cantidad (Mbps) debe ser un número entero.',
            'valor.required' => 'El valor es obligatorio.',
            'valor.numeric' => 'El valor debe ser un número.',
        ];
    }
}
