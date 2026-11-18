<?php

namespace App\Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecepcionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
        ];
    }
}
