<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Support\ReglasClave;
use Illuminate\Foundation\Http\FormRequest;

class CambiarClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Cualquier usuario autenticado cambia su propia contraseña.
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => array_merge(ReglasClave::reglas(), ['different:current_password']),
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Ingrese su contraseña actual.',
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'password.different' => 'La nueva contraseña debe ser distinta de la actual.',
        ] + ReglasClave::mensajes();
    }
}