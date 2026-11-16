<?php

namespace App\Modules\Printers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZonaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activa' => $this->boolean('activa')]);
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('zonas', 'nombre')->ignore($this->route('zona')?->id),
            ],
            'activa' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la zona es obligatorio.',
            'nombre.max' => 'El nombre de la zona no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe una zona con ese nombre.',
        ];
    }
}