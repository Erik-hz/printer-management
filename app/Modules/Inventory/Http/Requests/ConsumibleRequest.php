<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\Consumible;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConsumibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'codigo' => trim((string) $this->input('codigo')),
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('consumible')?->id;

        return [
            'tipo_consumible_id' => ['required', 'integer', 'exists:tipos_consumible,id'],
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('consumibles', 'codigo')->whereNull('deleted_at')->ignore($id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'color' => ['required', Rule::in(array_keys(Consumible::COLORES))],
            'rendimiento_paginas' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
            'activo' => ['boolean'],
            'modelos' => ['nullable', 'array'],
            'modelos.*' => ['integer', 'distinct', 'exists:modelos_impresora,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_consumible_id.required' => 'Seleccione el tipo de consumible.',
            'tipo_consumible_id.exists' => 'El tipo de consumible seleccionado no existe.',
            'codigo.required' => 'El código del consumible es obligatorio.',
            'codigo.max' => 'El código no puede superar los 50 caracteres.',
            'codigo.unique' => 'Ya existe un consumible con ese código.',
            'descripcion.max' => 'La descripción no puede superar los 255 caracteres.',
            'color.required' => 'Seleccione el color.',
            'color.in' => 'El color seleccionado no es válido.',
            'rendimiento_paginas.integer' => 'El rendimiento debe ser un número entero.',
            'rendimiento_paginas.min' => 'El rendimiento debe ser mayor que cero.',
            'rendimiento_paginas.max' => 'El rendimiento es demasiado grande.',
            'stock_minimo.required' => 'Indique el stock mínimo (0 si no aplica).',
            'stock_minimo.integer' => 'El stock mínimo debe ser un número entero.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
            'stock_minimo.max' => 'El stock mínimo es demasiado grande.',
            'modelos.array' => 'La lista de modelos compatibles no es válida.',
            'modelos.*.exists' => 'Uno de los modelos seleccionados no existe.',
            'modelos.*.distinct' => 'Hay modelos repetidos en la lista.',
            'modelos.*.integer' => 'Uno de los modelos seleccionados no es válido.',
        ];
    }
}