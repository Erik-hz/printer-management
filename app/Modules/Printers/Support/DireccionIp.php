<?php

namespace App\Modules\Printers\Support;

/**
 * Normaliza y valida direcciones IPv4. La reutilizará la importación del Excel,
 * que trae valores como "172.20.015.25" (cero a la izquierda).
 */
class DireccionIp
{
    /**
     * Recorta espacios, devuelve null si queda vacío y quita los ceros a la izquierda
     * de cada octeto. Lo que no parece una IP se devuelve intacto, para que la
     * validación lo rechace.
     */
    public static function normalizar(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if (! preg_match('/^\d{1,3}(\.\d{1,3}){3}$/', $valor)) {
            return $valor;
        }

        return implode('.', array_map('intval', explode('.', $valor)));
    }

    public static function esValida(?string $valor): bool
    {
        return $valor !== null && filter_var($valor, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}