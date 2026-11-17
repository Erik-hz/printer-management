<?php

namespace App\Modules\Printers\Http\Requests;

use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImpresoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorización la aplica el middleware can: de la ruta.
    }

    protected function prepareForValidation(): void
    {
        $limpiar = fn (?string $valor) => ($valor = trim((string) $valor)) === '' ? null : $valor;

        $this->merge([
            'nombre' => trim((string) $this->input('nombre')),
            'direccion_ip' => DireccionIp::normalizar($this->input('direccion_ip')),
            'equipo_conectado' => $limpiar($this->input('equipo_conectado')),
            'numero_serie' => $limpiar($this->input('numero_serie')),
            'observaciones' => $limpiar($this->input('observaciones')),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('impresora')?->id;

        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('impresoras', 'nombre')->whereNull('deleted_at')->ignore($id),
            ],
            'modelo_id' => ['required', 'integer', 'exists:modelos_impresora,id'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'estado_id' => ['required', 'integer', 'exists:estados_impresora,id'],
            'tipo_conexion' => ['required', Rule::in(['red', 'usb'])],
            'direccion_ip' => [
                'bail',
                'nullable',
                'required_if:tipo_conexion,red',
                'prohibited_if:tipo_conexion,usb',
                'ipv4',
                Rule::unique('impresoras', 'direccion_ip')->whereNull('deleted_at')->ignore($id),
            ],
            'equipo_conectado' => ['nullable', 'prohibited_if:tipo_conexion,red', 'string', 'max:100'],
            'numero_serie' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la impresora es obligatorio.',
            'nombre.max' => 'El nombre de la impresora no puede superar los 100 caracteres.',
            'nombre.unique' => 'Ya existe una impresora con ese nombre.',
            'modelo_id.required' => 'Seleccione el modelo.',
            'modelo_id.exists' => 'El modelo seleccionado no existe.',
            'area_id.required' => 'Seleccione el área.',
            'area_id.exists' => 'El área seleccionada no existe.',
            'estado_id.required' => 'Seleccione el estado.',
            'estado_id.exists' => 'El estado seleccionado no existe.',
            'tipo_conexion.required' => 'Seleccione el tipo de conexión.',
            'tipo_conexion.in' => 'El tipo de conexión debe ser red o USB.',
            'direccion_ip.required_if' => 'La dirección IP es obligatoria para las impresoras de red.',
            'direccion_ip.prohibited_if' => 'Las impresoras USB no tienen dirección IP.',
            'direccion_ip.ipv4' => 'La dirección IP no es válida. Use el formato 192.168.1.10.',
            'direccion_ip.unique' => 'Esa dirección IP ya está asignada a otra impresora.',
            'equipo_conectado.prohibited_if' => 'El equipo conectado solo aplica a las impresoras USB.',
            'equipo_conectado.max' => 'El nombre del equipo no puede superar los 100 caracteres.',
            'numero_serie.max' => 'El número de serie no puede superar los 50 caracteres.',
            'observaciones.max' => 'Las observaciones no pueden superar los 500 caracteres.',
        ];
    }
}