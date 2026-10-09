<?php

namespace App\Http\Requests\Isp;

use App\Models\Cliente;
use App\Models\Isp;
use App\Models\Scopes\IspScope;
use App\Models\TipoServicio;
use App\Enums\CategoriaIsp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class UpdateIspRequest extends FormRequest
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
                Rule::unique('isps', 'nombre')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('isp')),
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
     * No se puede pasar de "Gestión completa" a "Solo TV" mientras la ISP tenga
     * servicios en Corte o con un plan que no sea de TV (quedarían en un
     * estado/plan no permitido).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var Isp $isp */
            $isp = $this->route('isp');

            $nueva = CategoriaIsp::tryFrom((string) $this->input('categoria'));

            if ($isp->esPrincipal() || $isp->categoria !== CategoriaIsp::GestionCompleta || $nueva !== CategoriaIsp::SoloTv) {
                return;
            }

            $tvId = TipoServicio::where('nombre', 'TV')->value('id');

            $pendientes = Cliente::withoutGlobalScope(IspScope::class)
                ->where('isp_id', $isp->id)
                ->where(fn ($q) => $q
                    ->whereHas('estado', fn ($e) => $e->where('nombre', 'Corte'))
                    ->orWhereHas('plan', fn ($p) => $p->where('tipo_servicio_id', '!=', $tvId)))
                ->count();

            if ($pendientes > 0) {
                $validator->errors()->add('categoria', "No se puede pasar a Solo TV: hay {$pendientes} servicio(s) en Corte o con un plan que no es de TV. Cámbielos primero.");
            }
        });
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
