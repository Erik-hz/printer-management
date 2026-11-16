<?php

namespace App\Modules\Printers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LugarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['activo' => $this->boolean('activo')]);
    }

    public function rules(): array
    {
        $lugar = $this->route('lugar');
        $zonaId = $this->route('zona')?->id ?? $lugar?->zona_id;

        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('lugares', 'nombre')
                    ->where('zona_id', $zonaId)
                    ->ignore($lugar?->id),
            ],
            'activo' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del lugar es obligatorio.',
            'nombre.max' => 'El nombre del lugar no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un lugar con ese nombre en esta zona.',
        ];
    }
}