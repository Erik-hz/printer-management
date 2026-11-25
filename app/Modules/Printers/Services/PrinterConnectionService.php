<?php

namespace App\Modules\Printers\Services;

use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class PrinterConnectionService
{
    /** Comprueba HTTP y, si hay comunidad configurada, consulta SNMPv1 por UDP/161. */
    public function check(string $ip, int $timeout = 5, bool $checkSnmp = true): array
    {
        $ip = DireccionIp::normalizar($ip) ?? '';
        $checkedAt = now();

        if (! DireccionIp::esValida($ip)) {
            return $this->result($ip, 'error', false, null, null, 'La dirección no es una IPv4 válida.', $checkedAt);
        }

        $http = $this->checkHttp($ip, $timeout);
        $snmp = $checkSnmp ? $this->checkSnmp($ip, $timeout) : [
            'status' => 'skipped', 'available' => null, 'response_time_ms' => null,
            'error' => 'Comprobación SNMP desactivada.',
        ];

        $available = $http['available'] || $snmp['available'] === true;
        $hasNoResponse = $http['status'] === 'offline' || $snmp['status'] === 'offline';
        $status = $available ? 'online' : ($hasNoResponse ? 'offline' : 'error');
        $errors = array_filter([$http['error'], $snmp['error']]);

        return [
            'ip' => $ip,
            'status' => $status,
            'available' => $available,
            'http' => $http,
            'snmp' => $snmp,
            'response_time_ms' => $http['response_time_ms'] ?? $snmp['response_time_ms'],
            'checked_at' => $checkedAt,
            'error' => $errors ? implode(' | ', $errors) : null,
        ];
    }

    /** Comprueba si responde la interfaz web HTTP de la impresora. */
    public function checkHttp(string $ip, int $timeout = 5): array
    {
        $start = microtime(true);

        try {
            $response = Http::connectTimeout(min(3, $timeout))->timeout($timeout)->get("http://{$ip}");

            return [
                'status' => 'online', 'available' => true, 'http_code' => $response->status(),
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2), 'error' => null,
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => 'offline', 'available' => false, 'http_code' => null,
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2), 'error' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'error', 'available' => false, 'http_code' => null,
                'response_time_ms' => round((microtime(true) - $start) * 1000, 2), 'error' => $e->getMessage(),
            ];
        }
    }

    public function checkSnmp(string $ip, int $timeout = 2): array
    {
        $ip = DireccionIp::normalizar($ip) ?? '';
        if (! DireccionIp::esValida($ip)) {
            return ['status' => 'error', 'available' => false, 'version' => null, 'response_time_ms' => null, 'error' => 'La dirección no es una IPv4 válida.'];
        }

        $community = config('monitoring.snmp_community_get');
        if (! is_string($community)) {
            return ['status' => 'skipped', 'available' => null, 'version' => null, 'response_time_ms' => null, 'error' => 'SNMP omitido: SNMP_COMMUNITY_GET no está configurada.'];
        }

        $startedAt = microtime(true);
        $timeoutPerVersion = max(1, (int) ceil(max(1, $timeout) / 2));
        $oid = $this->ber(0x06, "\x2b\x06\x01\x02\x01\x01\x01\x00");
        $varBind = $this->ber(0x30, $oid.$this->ber(0x05, ''));
        $varBinds = $this->ber(0x30, $varBind);
        $errores = [];

        foreach ([0 => '1', 1 => '2c'] as $versionNumber => $versionName) {
            $socket = @stream_socket_client('udp://'.$ip.':161', $errno, $socketError, $timeoutPerVersion, STREAM_CLIENT_CONNECT);
            if ($socket === false) {
                $errores[] = "{$socketError} ({$errno})";
                continue;
            }

            try {
                stream_set_timeout($socket, $timeoutPerVersion);
                $requestId = random_int(1, 2147483646);
                $pdu = $this->ber(0xa0, $this->berInteger($requestId).$this->berInteger(0).$this->berInteger(0).$varBinds);
                $packet = $this->ber(0x30, $this->berInteger($versionNumber).$this->ber(0x04, $community).$pdu);

                if (@fwrite($socket, $packet) === false) {
                    $errores[] = "No se pudo enviar la consulta SNMPv{$versionName}.";
                    continue;
                }

                $response = @fread($socket, 65535);
                $metadata = stream_get_meta_data($socket);
                if ($response === false || $response === '' || ($metadata['timed_out'] ?? false)) {
                    continue;
                }

                if (! str_contains($response, $this->berInteger($versionNumber)) ||
                    ! str_contains($response, $this->berInteger($requestId)) ||
                    ! str_contains($response, "\xa2")) {
                    $errores[] = "La respuesta no coincide con SNMPv{$versionName}.";
                    continue;
                }

                return [
                    'status' => 'online',
                    'available' => true,
                    'version' => $versionName,
                    'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $errores[] = $e->getMessage();
            } finally {
                fclose($socket);
            }
        }

        return [
            'status' => 'offline',
            'available' => false,
            'version' => null,
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            'error' => $errores
                ? implode(' | ', $errores)
                : 'Sin respuesta SNMPv1 ni SNMPv2c en UDP 161 (timeout o comunidad de lectura no aceptada).',
        ];
    }
    private function result(string $ip, string $status, bool $available, ?int $httpCode, ?float $time, ?string $error, mixed $checkedAt): array
    {
        return [
            'ip' => $ip, 'status' => $status, 'available' => $available,
            'http' => ['status' => 'skipped', 'available' => false, 'http_code' => $httpCode, 'response_time_ms' => $time, 'error' => $error],
            'snmp' => ['status' => 'skipped', 'available' => null, 'response_time_ms' => null, 'error' => null],
            'response_time_ms' => $time, 'checked_at' => $checkedAt, 'error' => $error,
        ];
    }

    private function ber(int $tag, string $value): string
    {
        $length = strlen($value);
        if ($length < 128) {
            $encodedLength = chr($length);
        } else {
            $bytes = '';
            while ($length > 0) {
                $bytes = chr($length & 0xff).$bytes;
                $length >>= 8;
            }
            $encodedLength = chr(0x80 | strlen($bytes)).$bytes;
        }

        return chr($tag).$encodedLength.$value;
    }

    private function berInteger(int $number): string
    {
        $value = '';
        do {
            $value = chr($number & 0xff).$value;
            $number >>= 8;
        } while ($number > 0);
        if ((ord($value[0]) & 0x80) !== 0) {
            $value = "\x00".$value;
        }

        return $this->ber(0x02, $value);
    }
}
