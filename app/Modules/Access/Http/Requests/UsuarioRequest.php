<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Support\ReglasClave;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        $usuario = $this->route('usuario');

        $reglas = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:254',
                Rule::unique('users', 'email')->ignore($usuario?->id),
            ],
            'rol_id' => ['required', 'integer', 'exists:roles,id'],
            'activo' => ['boolean'],
        ];

        // La contraseña solo se pide al crear; después se cambia por una acción aparte.
        if (! $usuario) {
            $reglas['password'] = ReglasClave::reglas();
        }

        return $reglas;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede superar los 255 caracteres.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingrese un correo electrónico válido.',
            'email.max' => 'El correo electrónico es demasiado largo.',
            'email.unique' => 'Ya existe un usuario con ese correo electrónico.',
            'rol_id.required' => 'Seleccione el rol.',
            'rol_id.exists' => 'El rol seleccionado no existe.',
        ] + ReglasClave::mensajes();
    }
}