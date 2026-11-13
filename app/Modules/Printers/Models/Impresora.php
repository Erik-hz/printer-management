<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Impresora extends Model
{
    use SoftDeletes;

    protected $table = 'impresoras';

    protected $fillable = [
        'nombre',
        'modelo_id',
        'area_id',
        'estado_id',
        'tipo_conexion',
        'direccion_ip',
        'equipo_conectado',
        'numero_serie',
        'observaciones',
    ];

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ModeloImpresora::class, 'modelo_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoImpresora::class, 'estado_id');
    }

    public function esDeRed(): bool
    {
        return $this->tipo_conexion === 'red';
    }

    public function esUsb(): bool
    {
        return $this->tipo_conexion === 'usb';
    }
}