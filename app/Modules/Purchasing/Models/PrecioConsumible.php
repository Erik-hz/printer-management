<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Inventory\Models\Consumible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrecioConsumible extends Model
{
    protected $table = 'precios_consumible';

    protected $fillable = [
        'consumible_id',
        'proveedor_id',
        'precio',
        'moneda',
        'incluye_iva',
        'vigente_desde',
        'vigente_hasta',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'incluye_iva' => 'boolean',
            'vigente_desde' => 'date',
            'vigente_hasta' => 'date',
        ];
    }

    public function consumible(): BelongsTo
    {
        return $this->belongsTo(Consumible::class, 'consumible_id');
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}