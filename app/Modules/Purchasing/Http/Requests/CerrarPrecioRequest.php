<?php

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CerrarPrecioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vigente_hasta' => ['required', 'date'],
        ];
    }
}
