<?php

namespace App\Modules\Printers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AreaRequest extends FormRequest
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
        $area = $this->route('area');
        $lugarId = $this->route('lugar')?->id ?? $area?->lugar_id;

        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('areas', 'nombre')
                    ->where('lugar_id', $lugarId)
                    ->ignore($area?->id),
            ],
            'activa' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del área es obligatorio.',
            'nombre.max' => 'El nombre del área no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un área con ese nombre en este lugar.',
        ];
    }
}