<?php

namespace App\Modules\Monitoring\Jobs;

use App\Modules\Monitoring\Models\EjecucionLecturaSnmp;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class ActualizarLecturaSnmp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 80;

    public function __construct(
        public readonly int $ejecucionId,
        public readonly string $snmpVersion = '1',
    ) {}

    public function handle(): void
    {
        $ejecucion = EjecucionLecturaSnmp::query()->findOrFail($this->ejecucionId);
        $ejecucion->update([
            'estado' => 'ejecutando',
            'iniciada_en' => now(),
        ]);

        $script = base_path('scripts'.DIRECTORY_SEPARATOR.'collect_snmp_v1.ps1');
        $process = new Process([
            (string) config('monitoring.powershell_binary'),
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy',
            'RemoteSigned',
            '-File',
            $script,
            '-ConfigPath',
            (string) config('monitoring.snmp_config'),
            '-PrinterId',
            (string) $ejecucion->impresora_id,
            '-SnmpVersion',
            $this->snmpVersion,
        ]);
        $process->setWorkingDirectory(base_path());
        $process->setTimeout($this->timeout - 10);
        $process->run(null, [
            'MONITOREO_API_URL' => (string) config('monitoring.api_url'),
            'MONITOREO_TOKEN' => (string) config('monitoring.token'),
            'SNMP_COMMUNITY_GET' => (string) config('monitoring.snmp_community_get'),
        ]);

        $salida = trim($process->getOutput());
        if ($process->isSuccessful()) {
            $ejecucion->update([
                'estado' => 'completada',
                'salida' => Str::limit($salida, 8000, ''),
                'error' => null,
                'finalizada_en' => now(),
            ]);

            return;
        }

        $error = trim($process->getErrorOutput()) ?: $salida;
        $error = $error !== ''
            ? Str::limit($error, 3000, '')
            : 'El recolector PowerShell terminó con un código de error.';

        $ejecucion->update([
            'estado' => 'fallida',
            'salida' => Str::limit($salida, 8000, ''),
            'error' => $error,
            'finalizada_en' => now(),
        ]);

        Log::error('Falló la actualización SNMP de lecturas.', [
            'ejecucion_id' => $ejecucion->id,
            'impresora_id' => $ejecucion->impresora_id,
            'error' => $error,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $error = $exception?->getMessage() ?? 'No se pudo completar la actualización SNMP.';
        EjecucionLecturaSnmp::query()
            ->whereKey($this->ejecucionId)
            ->update([
                'estado' => 'fallida',
                'error' => Str::limit($error, 3000, ''),
                'finalizada_en' => now(),
            ]);

        Log::error('Falló la ejecución del recolector SNMP.', [
            'ejecucion_id' => $this->ejecucionId,
            'error' => $error,
        ]);
    }
}
