<?php

namespace App\Modules\Printers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    protected $table = 'areas';

    protected $fillable = ['lugar_id', 'nombre', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    public function lugar(): BelongsTo
    {
        return $this->belongsTo(Lugar::class, 'lugar_id');
    }
}