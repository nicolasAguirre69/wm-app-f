<?php

namespace App\Http\Requests\Titular;

use App\Http\Requests\Concerns\ReglasTitular;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edición de los datos de la PERSONA (titular). El cambio aplica a todos sus
 * servicios.
 */
class UpdateTitularRequest extends FormRequest
{
    use ReglasTitular;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarDatosTitular();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $titular = $this->route('titular');

        return $this->reglasTitular($titular->isp_id, $titular);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->mensajesTitular(editando: true);
    }
}
