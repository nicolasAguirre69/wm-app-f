<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', 'min:8'],
            // Solo el Super Admin elige el ISP (y se valida). El admin de ISP
            // usa el suyo, así que su isp_id (vacío) no debe validarse.
            'isp_id' => $this->user()->is_super_admin
                ? ['required', 'exists:isps,id']
                : ['nullable'],
            'rol' => ['required', 'string', Rule::exists('roles', 'name')],
            'activo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'Ya existe un usuario con ese correo.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'isp_id.required' => 'Debes seleccionar un ISP.',
            'rol.required' => 'Debes seleccionar un rol.',
        ];
    }
}
