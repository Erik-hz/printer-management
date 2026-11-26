<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Printers\Models\ModeloImpresora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Consumible extends Model
{
    use SoftDeletes;

    public const COLORES = [
        'negro' => 'Negro',
        'cian' => 'Cian',
        'magenta' => 'Magenta',
        'amarillo' => 'Amarillo',
        'tricolor' => 'Tricolor',
        'no_aplica' => 'No aplica',
    ];

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
        return [
            'activo' => 'boolean',
            'stock_minimo' => 'integer',
            'rendimiento_paginas' => 'integer',
        ];
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

    /**
     * Stock bajo: existencia menor que el mínimo. Con mínimo 0 no hay alerta.
     * Conviene cargar antes la relación "existencia" para no hacer una consulta por fila.
     */
    public function estaBajoMinimo(): bool
    {
        return $this->existencia !== null && $this->existencia->cantidad < $this->stock_minimo;
    }

    public function etiquetaColor(): string
    {
        if ($this->color !== 'no_aplica') {
            return self::COLORES[$this->color] ?? $this->color;
        }

        $descripcion = mb_strtoupper(Str::ascii((string) $this->descripcion));
        foreach ([
            'negro' => ['BLACK', 'NEGRO'],
            'cian' => ['CYAN', 'CIAN'],
            'magenta' => ['MAGENTA'],
            'amarillo' => ['YELLOW', 'AMARILLO'],
            'gris' => ['GRAY', 'GREY', 'GRIS'],
        ] as $etiqueta => $palabras) {
            foreach ($palabras as $palabra) {
                if (preg_match('/(^|[^A-Z])'.$palabra.'([^A-Z]|$)/', $descripcion)) {
                    return ucfirst($etiqueta);
                }
            }
        }

        return self::COLORES[$this->color] ?? $this->color;
    }
}
