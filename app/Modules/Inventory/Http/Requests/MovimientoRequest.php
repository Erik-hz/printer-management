<?php

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimientoRequest extends FormRequest
{
    public const TIPOS = ['entrada', 'salida', 'ajuste'];

    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $motivo = trim((string) $this->input('motivo'));
        $ticket = trim((string) $this->input('ticket'));

        $this->merge([
            'motivo' => $motivo === '' ? null : $motivo,
            'ticket' => $ticket === '' ? null : $ticket,
        ]);
    }

    public function rules(): array
    {
        $cantidad = ['required', 'integer'];

        if ($this->input('tipo') === 'ajuste') {
            // El ajuste lleva signo: positivo suma, negativo resta.
            array_push($cantidad, 'between:-1000000,1000000', 'not_in:0');
        } else {
            array_push($cantidad, 'min:1', 'max:1000000');
        }

        return [
            'tipo' => ['required', Rule::in(self::TIPOS)],
            'consumible_id' => ['required', 'integer', Rule::exists('consumibles', 'id')->whereNull('deleted_at')],
            'cantidad' => $cantidad,
            'impresora_id' => [
                'nullable',
                'required_if:tipo,salida',
                'prohibited_unless:tipo,salida',
                'integer',
                Rule::exists('impresoras', 'id')->whereNull('deleted_at'),
            ],
            'motivo' => ['nullable', 'required_if:tipo,entrada,ajuste', 'prohibited_if:tipo,salida', 'string', 'max:500'],
            'ticket' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Seleccione el tipo de movimiento.',
            'tipo.in' => 'El tipo de movimiento no es válido.',
            'consumible_id.required' => 'Seleccione el consumible.',
            'consumible_id.integer' => 'El consumible seleccionado no es válido.',
            'consumible_id.exists' => 'El consumible seleccionado no existe.',
            'cantidad.required' => 'Indique la cantidad.',
            'cantidad.integer' => 'La cantidad debe ser un número entero.',
            'cantidad.min' => 'La cantidad debe ser mayor que cero.',
            'cantidad.max' => 'La cantidad es demasiado grande.',
            'cantidad.between' => 'La cantidad del ajuste es demasiado grande.',
            'cantidad.not_in' => 'Un ajuste debe ser distinto de cero.',
            'impresora_id.required_if' => 'Seleccione la impresora de destino.',
            'impresora_id.prohibited_unless' => 'La impresora solo aplica a las salidas.',
            'impresora_id.integer' => 'La impresora seleccionada no es válida.',
            'impresora_id.exists' => 'La impresora seleccionada no existe.',
            'motivo.required_if' => 'Escriba el motivo del movimiento.',
            'motivo.prohibited_if' => 'Las salidas no llevan motivo.',
            'motivo.max' => 'El motivo no puede superar los 500 caracteres.',
            'ticket.max' => 'El ticket no puede superar los 100 caracteres.',
        ];
    }
}