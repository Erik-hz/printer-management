<?php

namespace App\Modules\Monitoring\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LecturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $contador = trim((string) $this->input('contador_paginas'));
        $motivo = trim((string) $this->input('motivo_sin_lectura_id'));
        $niveles = [];
        $tipoNivel = $this->input('nivel_tipo_consumible_id');
        $colorNivel = $this->input('nivel_color');
        $valorNivel = trim((string) $this->input('nivel'));

        if ($tipoNivel && $colorNivel && $valorNivel !== '') {
            $niveles[] = [
                'tipo_consumible_id' => (int) $tipoNivel,
                'color' => $colorNivel,
                'nivel' => (float) $valorNivel,
            ];
        }

        $this->merge([
            'contador_paginas' => $contador === '' ? null : (int) $contador,
            'motivo_sin_lectura_id' => $motivo === '' ? null : (int) $motivo,
            'observaciones' => trim((string) $this->input('observaciones')) ?: null,
            'niveles' => $niveles,
        ]);
    }

    public function rules(): array
    {
        $niveles = $this->input('niveles');
        $tieneNivel = is_array($niveles) && collect($niveles)->contains(
            fn ($nivel) => is_array($nivel) && isset($nivel['tipo_consumible_id'], $nivel['color'], $nivel['nivel'])
        );

        return [
            'impresora_id' => ['required', 'integer', 'exists:impresoras,id'],
            'fecha_lectura' => ['required', 'date'],
            'contador_paginas' => ['nullable', 'required_without_all:motivo_sin_lectura_id,niveles', 'integer', 'min:0'],
            'motivo_sin_lectura_id' => [
                'nullable',
                'required_without_all:contador_paginas,niveles',
                'prohibited_with:contador_paginas',
                'exists:motivos_sin_lectura,id',
            ],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'niveles' => ['nullable', 'array', 'max:20'],
            'niveles.*.tipo_consumible_id' => ['required_with:niveles', 'integer', 'exists:tipos_consumible,id'],
            'niveles.*.color' => [
                'required_with:niveles',
                Rule::in(['negro', 'cian', 'magenta', 'amarillo', 'tricolor', 'no_aplica']),
            ],
            'niveles.*.nivel' => ['required_with:niveles', 'numeric', 'between:0,1'],
        ];
    }

    public function messages(): array
    {
        return [
            'contador_paginas.required_without_all' => 'Informe el contador, los niveles o un motivo sin lectura.',
            'motivo_sin_lectura_id.required_without_all' => 'Informe el contador, los niveles o un motivo sin lectura.',
            'motivo_sin_lectura_id.prohibited_with' => 'No puede informar contador y motivo al mismo tiempo.',
            'niveles.array' => 'Los niveles deben enviarse como una lista.',
            'niveles.*.nivel.between' => 'El nivel debe estar entre 0 y 1.',
        ];
    }
}
