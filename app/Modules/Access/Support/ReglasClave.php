<?php

namespace App\Modules\Access\Support;

/** Política de contraseñas, definida en un solo lugar. */
class ReglasClave
{
    /** @return array<int, string> */
    public static function reglas(): array
    {
        return ['required', 'string', 'min:10', 'max:72', 'regex:/[A-Za-z]/', 'regex:/\d/', 'confirmed'];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.max' => 'La contraseña no puede superar los 72 caracteres.',
            'password.regex' => 'La contraseña debe incluir al menos una letra y un número.',
            'password.confirmed' => 'La confirmación de la contraseña no coincide.',
        ];
    }
}