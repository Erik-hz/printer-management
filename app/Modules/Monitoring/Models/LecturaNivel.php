<?php

namespace App\Modules\Monitoring\Models;

use App\Modules\Inventory\Models\TipoConsumible;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturaNivel extends Model
{
    public $timestamps = false;

    protected $table = 'lecturas_nivel';

    protected $fillable = [
        'lectura_id',
        'tipo_consumible_id',
        'color',
        'nivel',
    ];

    protected function casts(): array
    {
        return [
            'nivel' => 'decimal:4',
        ];
    }

    public function lectura(): BelongsTo
    {
        return $this->belongsTo(Lectura::class, 'lectura_id');
    }

    public function tipoConsumible(): BelongsTo
    {
        return $this->belongsTo(TipoConsumible::class, 'tipo_consumible_id');
    }
}
