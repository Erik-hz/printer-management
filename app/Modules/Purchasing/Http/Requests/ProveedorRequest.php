<?php

namespace App\Modules\Purchasing\Http\Requests;

use App\Modules\Purchasing\Models\Proveedor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $proveedor = $this->route('proveedor');

        return [
            'nombre' => [
                'required',
                'string',
                'max:150',
                Rule::unique('proveedores', 'nombre')
                    ->whereNull('deleted_at')
                    ->ignore($proveedor instanceof Proveedor ? $proveedor->id : null),
            ],
            'nit' => ['nullable', 'string', 'max:30'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'correo' => ['nullable', 'email', 'max:254'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
