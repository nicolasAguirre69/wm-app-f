<?php

namespace App\Http\Requests\Concerns;

use App\Enums\TipoIdentificacion;
use App\Models\Titular;
use Illuminate\Validation\Rule;

/**
 * Reglas de los datos de la PERSONA (titular). Se usan al crear un cliente
 * nuevo y al editar un titular, para que ambos validen exactamente igual.
 */
trait ReglasTitular
{
    /**
     * Limpia antes de validar: teléfonos solo con dígitos e identificación sin
     * espacios en los extremos (así coinciden con lo que guarda Titular).
     */
    protected function limpiarDatosTitular(): void
    {
        $limpio = [];

        foreach (['telefono_1', 'telefono_2'] as $campo) {
            if ($this->filled($campo)) {
                $limpio[$campo] = preg_replace('/\D/', '', (string) $this->input($campo));
            }
        }

        if ($this->filled('identificacion')) {
            $limpio['identificacion'] = trim((string) $this->input('identificacion'));
        }

        $this->merge($limpio);
    }

    /**
     * @param  int|null  $ispId  ISP donde la identificación debe ser única.
     * @param  Titular|null  $ignorar  Titular que se edita (no choca consigo mismo).
     * @return array<string, mixed>
     */
    protected function reglasTitular(?int $ispId, ?Titular $ignorar = null): array
    {
        $unica = Rule::unique('titulares', 'identificacion')->where('isp_id', $ispId);

        if ($ignorar) {
            $unica->ignore($ignorar->id);
        } else {
            // Al crear: un titular archivado (sin servicios) se reutiliza.
            $unica->whereNull('deleted_at');
        }

        return [
            'tipo_identificacion' => ['required', Rule::enum(TipoIdentificacion::class)],
            // Una persona por ISP + identificación.
            'identificacion' => ['required', 'string', 'max:20', $unica],
            // tipo_contribuyente no se pide: Titular lo calcula a partir del
            // tipo de identificación (CC, NIT, CE...).

            'primer_nombre' => ['required', 'string', 'max:255'],
            'segundo_nombre' => ['nullable', 'string', 'max:255'],
            // Para NIT (empresa) la razón social va en primer_nombre: sin apellido.
            'primer_apellido' => ['nullable', 'required_unless:tipo_identificacion,NIT', 'string', 'max:255'],
            'segundo_apellido' => ['nullable', 'string', 'max:255'],

            'telefono_1' => ['required', 'digits_between:7,11'],
            'telefono_2' => ['nullable', 'digits_between:7,11'],
            'correo' => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function mensajesTitular(bool $editando = false): array
    {
        return [
            'identificacion.unique' => $editando
                ? 'Ya existe otra persona con esa identificación en esta ISP.'
                : 'Esta persona ya está registrada en tu ISP. Búsquela y use "Agregar servicio" en su fila.',
            'identificacion.max' => 'La identificación no puede superar 20 caracteres.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'primer_apellido.required_unless' => 'El primer apellido es obligatorio (salvo para NIT).',
            'telefono_1.digits_between' => 'El teléfono 1 debe tener entre 7 y 11 dígitos.',
            'telefono_2.digits_between' => 'El teléfono 2 debe tener entre 7 y 11 dígitos.',
        ];
    }
}
