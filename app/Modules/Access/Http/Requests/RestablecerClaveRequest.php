<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Support\ReglasClave;
use Illuminate\Foundation\Http\FormRequest;

class RestablecerClaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    public function rules(): array
    {
        return ['password' => ReglasClave::reglas()];
    }

    public function messages(): array
    {
        return ReglasClave::mensajes();
    }
}