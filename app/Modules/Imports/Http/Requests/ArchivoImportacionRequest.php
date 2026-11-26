<?php

namespace App\Modules\Imports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArchivoImportacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:10240'],
            'tipo_importacion' => ['nullable', 'in:lecturas,consumibles'],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required' => 'Seleccione el archivo Excel que desea analizar.',
            'archivo.mimes' => 'El archivo debe ser un libro de Excel .xlsx.',
            'archivo.extensions' => 'El archivo debe tener extensión .xlsx.',
            'archivo.max' => 'El archivo no puede superar los 10 MB.',
        ];
    }
}
