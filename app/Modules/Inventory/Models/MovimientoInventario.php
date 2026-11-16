<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Printers\Models\Impresora;
use App\Modules\Purchasing\Models\DetalleCompra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class MovimientoInventario extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'consumible_id',
        'tipo',
        'cantidad',
        'saldo_resultante',
        'detalle_compra_id',
        'impresora_id',
        'ticket',
        'motivo',
        'usuario_id',
        'importacion_id',
        'fecha_movimiento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'saldo_resultante' => 'integer',
            'fecha_movimiento' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new LogicException('Los movimientos de inventario no se pueden editar.');
        });

        static::deleting(static function (): void {
            throw new LogicException('Los movimientos de inventario no se pueden borrar.');
        });
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class, 'consumible_id')->withTrashed();
    }

    public function detalleCompra(): BelongsTo
    {
        return $this->belongsTo(DetalleCompra::class, 'detalle_compra_id');
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class, 'impresora_id')->withTrashed();
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }
}
