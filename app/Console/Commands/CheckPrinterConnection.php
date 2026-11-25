<?php

namespace App\Console\Commands;

use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Services\PrinterConnectionService;
use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Console\Command;
use Throwable;

class CheckPrinterConnection extends Command
{
    protected $signature = 'printers:check {ip? : IP de la impresora} {--all : Comprueba todas las impresoras de red registradas} {--timeout=5 : Tiempo máximo de espera en segundos}';

    protected $description = 'Comprueba conectividad HTTP y SNMP de una impresora o de todas las registradas';

    public function handle(PrinterConnectionService $monitor): int
    {
        $ip = $this->argument('ip');
        $timeout = filter_var($this->option('timeout'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 30]]);

        if ($timeout === false) {
            $this->error('El timeout debe ser un entero entre 1 y 30 segundos.');

            return self::INVALID;
        }

        if ($ip && $this->option('all')) {
            $this->error('Indica una IP o --all, pero no ambos.');

            return self::INVALID;
        }

        if (! $ip && ! $this->option('all')) {
            $this->error('Indica una IP o usa --all para consultar las impresoras registradas.');

            return self::INVALID;
        }

        try {
            $printers = $ip
                ? collect([(object) ['id' => null, 'nombre' => 'Prueba independiente', 'direccion_ip' => $ip]])
                : Impresora::query()
                    ->where('tipo_conexion', 'red')
                    ->whereNotNull('direccion_ip')
                    ->orderBy('id')
                    ->get(['id', 'nombre', 'direccion_ip']);
        } catch (Throwable $e) {
            $this->error('No se pudieron consultar las impresoras registradas: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($printers->isEmpty()) {
            $this->warn('No hay impresoras de red registradas con dirección IP.');

            return self::SUCCESS;
        }

        $rows = [];
        $failures = 0;

        foreach ($printers as $printer) {
            $result = $monitor->check((string) $printer->direccion_ip, (int) $timeout);
            $rows[] = [
                $printer->id ?? '—',
                $printer->nombre,
                $result['ip'],
                strtoupper($result['status']),
                $result['http']['http_code'] ?? '—',
                strtoupper($result['snmp']['status']),
                $result['response_time_ms'] ?? '—',
            ];

            if (! $result['available']) {
                $failures++;
                $this->line("Detalle {$result['ip']}: ".($result['error'] ?? 'Sin respuesta.'));
            }
        }

        $this->newLine();
        $this->table(['ID', 'Impresora', 'IP', 'Estado', 'HTTP', 'SNMP/161', 'Tiempo (ms)'], $rows);
        $this->newLine();
        $this->info(($printers->count() - $failures)." de {$printers->count()} impresora(s) accesible(s).");

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
