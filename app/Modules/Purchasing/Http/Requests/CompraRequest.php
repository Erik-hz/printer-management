<?php

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->where('activo', true)->whereNull('deleted_at')],
            'numero_documento' => ['nullable', 'string', 'max:100'],
            'fecha_orden' => ['required', 'date'],
            'moneda' => ['required', 'string', 'size:3', 'alpha'],
            'incluye_iva' => ['required', 'boolean'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.consumible_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('consumibles', 'id')->where('activo', true)->whereNull('deleted_at'),
            ],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ];
    }

    public function messages(): array
    {
        return [
            'proveedor_id.exists' => 'Seleccione un proveedor activo.',
            'detalles.required' => 'Agregue al menos un consumible a la orden.',
            'detalles.*.consumible_id.distinct' => 'No repita consumibles; indique una sola línea por consumible.',
            'detalles.*.consumible_id.exists' => 'Seleccione un consumible activo.',
            'detalles.*.cantidad.min' => 'La cantidad ordenada debe ser mayor que cero.',
        ];
    }
}
