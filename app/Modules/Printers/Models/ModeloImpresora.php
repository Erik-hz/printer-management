<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use App\Modules\Inventory\Models\Consumible;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeloImpresora extends Model
{
    protected $table = 'modelos_impresora';

    protected $fillable = [
        'marca_id',
        'nombre',
        'tipo_impresion',
        'tecnologia',
        'snmp_soportado',
    ];

    protected function casts(): array
    {
        return ['snmp_soportado' => 'boolean'];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function impresoras(): HasMany
    {
        return $this->hasMany(Impresora::class, 'modelo_id');
    }
    public function consumibles(): BelongsToMany
    {
        return $this->belongsToMany(
            Consumible::class,
            'modelo_consumible',
            'modelo_id',
            'consumible_id'
        );
    }
}