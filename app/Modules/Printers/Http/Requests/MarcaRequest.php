<?php

namespace App\Modules\Printers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarcaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:50',
                Rule::unique('marcas', 'nombre')->ignore($this->route('marca')?->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la marca es obligatorio.',
            'nombre.max' => 'El nombre de la marca no puede superar los 50 caracteres.',
            'nombre.unique' => 'Ya existe una marca con ese nombre.',
        ];
    }
}