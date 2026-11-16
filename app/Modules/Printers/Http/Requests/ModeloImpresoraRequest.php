<?php

namespace App\Modules\Printers\Http\Requests;

use App\Modules\Printers\Models\ModeloImpresora;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModeloImpresoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    public function rules(): array
    {
        return [
            'marca_id' => ['required', 'integer', 'exists:marcas,id'],
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('modelos_impresora', 'nombre')
                    ->where('marca_id', $this->input('marca_id'))
                    ->ignore($this->route('modelo')?->id),
            ],
            'tipo_impresion' => ['required', Rule::in(array_keys(ModeloImpresora::TIPOS_IMPRESION))],
            'tecnologia' => ['required', Rule::in(array_keys(ModeloImpresora::TECNOLOGIAS))],
            'snmp_soportado' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'marca_id.required' => 'Seleccione la marca.',
            'marca_id.integer' => 'La marca seleccionada no es válida.',
            'marca_id.exists' => 'La marca seleccionada no existe.',
            'nombre.required' => 'El nombre del modelo es obligatorio.',
            'nombre.max' => 'El nombre del modelo no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe un modelo con ese nombre para la marca seleccionada.',
            'tipo_impresion.required' => 'Seleccione el tipo de impresión.',
            'tipo_impresion.in' => 'El tipo de impresión seleccionado no es válido.',
            'tecnologia.required' => 'Seleccione la tecnología.',
            'tecnologia.in' => 'La tecnología seleccionada no es válida.',
            'snmp_soportado.boolean' => 'El valor de compatibilidad SNMP no es válido.',
        ];
    }
}