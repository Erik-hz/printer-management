<?php

namespace App\Modules\Monitoring\Services;

use App\Modules\Printers\Models\Impresora;
use App\Modules\Printers\Support\DireccionIp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ImpresorasDisponiblesSnmp
{
    public function listar(?int $printerId = null): Collection
    {
        return Impresora::query()
            ->where('tipo_conexion', 'red')
            ->whereNotNull('direccion_ip')
            ->whereHas('estado', fn (Builder $query) => $query->where('es_operativo', true))
            ->whereHas('modelo', function (Builder $query): void {
                $query->where(fn (Builder $query) => $query->where('snmp_soportado', true)->orWhereNull('snmp_soportado'));
            })
            ->when($printerId !== null, fn (Builder $query) => $query->whereKey($printerId))
            ->orderBy('id')
            ->get(['id', 'direccion_ip'])
            ->filter(fn (Impresora $impresora) => DireccionIp::esPrivada($impresora->direccion_ip))
            ->values();
    }
}
