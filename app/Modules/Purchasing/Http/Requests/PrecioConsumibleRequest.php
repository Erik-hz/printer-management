<?php

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrecioConsumibleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'consumible_id' => ['required', 'integer', Rule::exists('consumibles', 'id')->where('activo', true)->whereNull('deleted_at')],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)->whereNull('deleted_at')],
            'precio' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'incluye_iva' => ['required', 'boolean'],
            'vigente_desde' => ['required', 'date'],
            'vigente_hasta' => ['nullable', 'date', 'after_or_equal:vigente_desde'],
        ];
    }

    public function messages(): array
    {
        return [
            'consumible_id.exists' => 'Seleccione un consumible activo.',
            'proveedor_id.exists' => 'Seleccione un proveedor activo o deje el precio como general.',
        ];
    }
}
