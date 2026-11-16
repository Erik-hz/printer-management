<?php

namespace App\Modules\Monitoring\Models;

use App\Models\User;
use App\Modules\Imports\Models\Importacion;
use App\Modules\Printers\Models\Impresora;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lectura extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'lecturas';

    protected $fillable = [
        'impresora_id',
        'fecha_lectura',
        'origen',
        'contador_paginas',
        'motivo_sin_lectura_id',
        'observaciones',
        'usuario_id',
        'importacion_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_lectura' => 'datetime',
            'contador_paginas' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function impresora(): BelongsTo
    {
        return $this->belongsTo(Impresora::class, 'impresora_id')->withTrashed();
    }

    public function motivoSinLectura(): BelongsTo
    {
        return $this->belongsTo(MotivoSinLectura::class, 'motivo_sin_lectura_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(LecturaNivel::class, 'lectura_id');
    }
}
