<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Inventory\Models\Consumible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class DetalleCompra extends Model
{
    protected $table = 'detalle_compra';

    protected $fillable = [
        'compra_id',
        'consumible_id',
        'cantidad',
        'cantidad_recibida',
        'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'cantidad_recibida' => 'integer',
            'precio_unitario' => 'decimal:2',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class, 'consumible_id')->withTrashed();
    }

}
