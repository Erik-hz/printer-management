<?php

namespace App\Modules\Printers\Models;

use App\Modules\Inventory\Models\MovimientoInventario;
use App\Modules\Monitoring\Models\Lectura;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Impresora extends Model
{
    use SoftDeletes;

    protected $table = 'impresoras';

    protected $casts = [
        'modelo_id' => 'integer',
        'area_id' => 'integer',
        'estado_id' => 'integer',
    ];

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

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'impresora_id');
    }

    public function lecturas(): HasMany
    {
        return $this->hasMany(Lectura::class, 'impresora_id');
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
