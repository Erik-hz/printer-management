<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Printers\Models\ModeloImpresora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consumible extends Model
{
    use SoftDeletes;

    protected $table = 'consumibles';

    protected $fillable = [
        'tipo_consumible_id',
        'codigo',
        'descripcion',
        'color',
        'rendimiento_paginas',
        'stock_minimo',
        'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoConsumible::class, 'tipo_consumible_id');
    }

    public function modelos(): BelongsToMany
    {
        return $this->belongsToMany(
            ModeloImpresora::class,
            'modelo_consumible',
            'consumible_id',
            'modelo_id'
        );
    }

    public function existencia(): HasOne
    {
        return $this->hasOne(Existencia::class, 'consumible_id');
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'consumible_id');
    }
}
